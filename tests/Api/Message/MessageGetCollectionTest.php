<?php

namespace App\Tests\Api\Message;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class MessageGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->client->request('GET', '/api/messages/00000000-0000-0000-0000-000000000000',);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_collection(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $thread = MessageThreadFactory::new()->create();
        $thread2 = MessageThreadFactory::new()->create();
        $message1 = MessageFactory::new([
            'author'           => $user1, 'thread' => $thread,
            'content'          => 'last message from user 1',
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
        ])->create(); // should be the last message in the order
        $thread->lastMessage = $message1;
        \Zenstruck\Foundry\Persistence\save($thread);
        $message2 = MessageFactory::new([
            'author'           => $user1, 'thread' => $thread,
            'content'          => 'first message from user 1',
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2023-01-02T02:03:04+00:00'),
        ])->create();// should be the first
        $message3 = MessageFactory::new([
            'author'           => $user2, 'thread' => $thread,
            'content'          => 'second message from user 2',
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
        ])->create();// should be the second
        $message4 = MessageFactory::new([
            'author'           => $user1, 'thread' => $thread,
            'content'          => 'third message from user 1',
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
        ])->create(); // should be the third
        $otherMessage = MessageFactory::new([
            'author'           => $user1, 'thread' => $thread2,
            'content'          => 'other message',
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
        ])->create(); // this message is in another thread
        $thread2->lastMessage = $otherMessage;
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        //this is other thread participant
        MessageParticipantFactory::new(['thread' => $thread2, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread2, 'participant' => $user2])->create();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/messages/' . $thread->id . '?order[creation_datetime]=desc');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/Message',
            '@id'              => '/api/messages/' . $thread->id,
            '@type'            => 'Collection',
            'member'     => [
                [
                    '@id' => '/api/messages/' . $message2->id,
                    '@type' => 'Message',
                    'creation_datetime' => '2023-01-02T02:03:04+00:00',
                    'author'            => [
                        '@id' => '/api/users/' . $user1->id,
                        '@type' => 'User',
                        'id'       => $user1->id,
                        'username' => 'base_user_1',
                    ],
                    'content'           => 'first message from user 1',
                ],
                [
                    '@id' => '/api/messages/' . $message3->id,
                    '@type' => 'Message',
                    'creation_datetime' => '2022-01-02T02:03:04+00:00',
                    'author'            => [
                        '@id' => '/api/users/' . $user2->id,
                        '@type' => 'User',
                        'id'       => $user2->id,
                        'username' => 'base_user_2',
                    ],
                    'content'           => 'second message from user 2',
                ],
                [
                    '@id' => '/api/messages/' . $message4->id,
                    '@type' => 'Message',
                    'creation_datetime' => '2021-01-02T02:03:04+00:00',
                    'author'            => [
                        '@id' => '/api/users/' . $user1->id,
                        '@type' => 'User',
                        'id'       => $user1->id,
                        'username' => 'base_user_1',
                    ],
                    'content'           => 'third message from user 1',
                ],
                [
                    '@id' => '/api/messages/' . $message1->id,
                    '@type' => 'Message',
                    'creation_datetime' => '2020-01-02T02:03:04+00:00',
                    'author'            => [
                        '@id' => '/api/users/' . $user1->id,
                        '@type' => 'User',
                        'id'       => $user1->id,
                        'username' => 'base_user_1',
                    ],
                    'content'           => 'last message from user 1',
                ],
            ],
            'totalItems' => 4,
            'view'       => [
                '@id'   => '/api/messages/' . $thread->id . '?order%5Bcreation_datetime%5D=desc',
                '@type' => 'PartialCollectionView',
            ],
            'search'     => [
                '@type'                        => 'IriTemplate',
                'template'               => '/api/messages/' . $thread->id . '{?order[creation_datetime]}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping'                => [
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'order[creation_datetime]',
                        'property' => 'creation_datetime',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_xss_payload_is_sanitized_in_message_content(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        $message = MessageFactory::new([
            'author' => $user2,
            'thread' => $thread,
            'content' => '<img src=x onerror="alert(1)"><a href="javascript:alert(1)">click</a><script>alert(1)</script>',
        ])->create();
        $thread->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($thread);

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/messages/' . $thread->id);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'member' => [
                [
                    'content' => '',
                ],
            ],
        ]);
    }

    public function test_get_collection_but_not_participant(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);
        $user3 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_3', 'email' => 'base_user3@email.com']);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();

        $this->client->loginUser($user3); // user3 is not part of this thread
        $this->client->request('GET', '/api/messages/' . $thread->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title'       => 'An error occurred',
            'description' => 'Vous n\'êtes pas autorisé à voir ceci.',
            'detail' => 'Vous n\'êtes pas autorisé à voir ceci.',
            'status' => 403,
            'type' => '/errors/403',
            '@context' => '/api/contexts/Error',
        ]);
    }
}