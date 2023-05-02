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

class MessageGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_not_logged()
    {
        $this->client->request('GET', '/api/messages/123',);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_collection()
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $thread = MessageThreadFactory::new()->create();
        $thread2 = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        //this is other thread participant
        MessageParticipantFactory::new(['thread' => $thread2, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread2, 'participant' => $user2])->create();
        $message1 = MessageFactory::new([
            'author'           => $user1, 'thread' => $thread,
            'content'          => 'last message from user 1',
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
        ])->create(); // should be the last message in the order
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

        $thread = $thread->object();
        $user1 = $user1->object();
        $user2 = $user2->object();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/messages/' . $thread->getId() . '?order[creation_datetime]=desc');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/Message',
            '@id'              => '/api/messages/' . $thread->getId(),
            '@type'            => 'hydra:Collection',
            'hydra:member'     => [
                [
                    'creation_datetime' => '2023-01-02T02:03:04+00:00',
                    'author'            => [
                        'id'       => $user1->getId(),
                        'username' => 'base_user_1',
                    ],
                    'content'           => 'first message from user 1',
                ],
                [
                    'creation_datetime' => '2022-01-02T02:03:04+00:00',
                    'author'            => [
                        'id'       => $user2->getId(),
                        'username' => 'base_user_2',
                    ],
                    'content'           => 'second message from user 2',
                ],
                [
                    'creation_datetime' => '2021-01-02T02:03:04+00:00',
                    'author'            => [
                        'id'       => $user1->getId(),
                        'username' => 'base_user_1',
                    ],
                    'content'           => 'third message from user 1',
                ],
                [
                    'creation_datetime' => '2020-01-02T02:03:04+00:00',
                    'author'            => [
                        'id'       => $user1->getId(),
                        'username' => 'base_user_1',
                    ],
                    'content'           => 'last message from user 1',
                ],
            ],
            'hydra:totalItems' => 4,
            'hydra:view'       => [
                '@id'   => '/api/messages/' . $thread->getId() . '?order%5Bcreation_datetime%5D=desc',
                '@type' => 'hydra:PartialCollectionView',
            ],
            'hydra:search'     => [
                '@type'                        => 'hydra:IriTemplate',
                'hydra:template'               => '/api/messages/' . $thread->getId() . '{?order[creation_datetime]}',
                'hydra:variableRepresentation' => 'BasicRepresentation',
                'hydra:mapping'                => [
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

    public function test_get_collection_but_not_participant()
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);
        $user3 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_3', 'email' => 'base_user3@email.com']);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();

        $thread = $thread->object();

        $this->client->loginUser($user3->object()); // user3 is not part of this thread
        $this->client->request('GET', '/api/messages/' . $thread->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context'          => '/api/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Vous n\'êtes pas autorisé à voir ceci.',
        ]);
    }
}