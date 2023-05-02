<?php

namespace App\Tests\Api\Message;

use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MessagePostInThreadTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_not_logged()
    {
        $thread = MessageThreadFactory::new()->create();
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread'  => '/api/message_threads/' . $thread->object()->getId(),
            'content' => 'content',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_post_message_in_thread()
    {
        $messageRepository = static::getContainer()->get(MessageRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $thread])->create();

        $user1 = $user1->object();
        $thread = $thread->object();

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread'  => '/api/message_threads/' . $thread->getId(),
            'content' => 'new content from user1',
        ]);
        $messages = $messageRepository->findBy(['thread' => $thread]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            'id'                => $messages[0]->getId(),
            'creation_datetime' => $messages[0]->getCreationDatetime()->format('c'),
            'author'            => [
                'id'       => $user1->getId(),
                'username' => 'base_user_1',
            ],
            'thread'            => [
                'id' => $thread->getId(),
            ],
            'content'           => 'new content from user1',
        ]);
    }

    public function test_post_message_in_thread_but_not_in_participants()
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);
        $user3 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_3', 'email' => 'base_user3@email.com']);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $thread])->create();

        $user3 = $user3->object();
        $thread = $thread->object();

        $this->client->loginUser($user3);
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread'  => '/api/message_threads/' . $thread->getId(),
            'content' => 'new content from user1',
        ], ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context'          => '/api/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Vous n\'êtes pas autorisé à voir ceci.',
        ]);
    }

    public function test_with_invalid_values()
    {
        $thread = MessageThreadFactory::new()->create();
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread'  => '/api/message_threads/' . $thread->object()->getId(),
            'content' => '',
        ], ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context'          => '/api/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'content: Cette valeur ne doit pas être vide.',
            'violations'        => [
                [
                    'propertyPath' => 'content',
                    'message'      => 'Cette valeur ne doit pas être vide.',
                    'code'         => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
        ]);
    }
}