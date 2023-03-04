<?php

namespace App\Tests\Api\Message;

use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MessageUserPostTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_not_logged()
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->object();
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $user1->getId(),
            'content'   => 'content',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_post_message()
    {
        $messageRepository = static::getContainer()->get(MessageRepository::class);
        $messageThreadMetaRepository = static::getContainer()->get(MessageThreadMetaRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $user1 = $user1->object();
        $user2 = $user2->object();

        $this->assertCount(0, $messageThreadMetaRepository->findBy(['user' => $user1]));
        $this->assertCount(0, $messageThreadMetaRepository->findBy(['user' => $user2]));
        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $user2->getId(),
            'content' => 'new content from user1',
        ]);
        $resultUser1 = $messageThreadMetaRepository->findBy(['user' => $user1]);
        $resultUser2 = $messageThreadMetaRepository->findBy(['user' => $user2]);
        // check we have message meta thread items for both user
        $this->assertCount(1, $resultUser1);
        $this->assertCount(1, $resultUser2);
        $this->assertSame($resultUser1[0]->getThread(), $resultUser2[0]->getThread()); // the thread must be the same
        // get the messages from this thread
        $messages = $messageRepository->findBy(['thread' => $resultUser1[0]->getThread()]);
        $this->assertCount(1, $messages);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            'id'                => $messages[0]->getId(),
            'creation_datetime' => $messages[0]->getCreationDatetime()->format('c'),
            'author'            => [
                'id'       => $user1->getId(),
                'username' => 'base_user_1',
            ],
            'thread'            => ['id' => $resultUser1[0]->getThread()->getId()],
            'content'           => 'new content from user1',
        ]);
    }

    public function test_post_message_but_a_thread_exist()
    {
        $messageRepository = static::getContainer()->get(MessageRepository::class);
        $messageThreadMetaRepository = static::getContainer()->get(MessageThreadMetaRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $thread])->create();

        $user1 = $user1->object();
        $user2 = $user2->object();
        $thread = $thread->object();

        // we already have meta thread per user
        $this->assertCount(1, $messageThreadMetaRepository->findBy(['user' => $user1]));
        $this->assertCount(1, $messageThreadMetaRepository->findBy(['user' => $user2]));
        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $user2->getId(),
            'content' => 'new content from user1',
        ]);
        $resultUser1 = $messageThreadMetaRepository->findBy(['user' => $user1]);
        $resultUser2 = $messageThreadMetaRepository->findBy(['user' => $user2]);
        // check we still have only 1 message meta thread per user
        $this->assertCount(1, $resultUser1);
        $this->assertCount(1, $resultUser2);
        $this->assertSame($thread, $resultUser1[0]->getThread());
        $this->assertSame($thread, $resultUser2[0]->getThread());
        // get the messages from this thread
        $messages = $messageRepository->findBy(['thread' => $resultUser1[0]->getThread()]);
        $this->assertCount(1, $messages);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            'id'                => $messages[0]->getId(),
            'creation_datetime' => $messages[0]->getCreationDatetime()->format('c'),
            'author'            => [
                'id'       => $user1->getId(),
                'username' => 'base_user_1',
            ],
            'thread'            => ['id' => $resultUser1[0]->getThread()->getId()],
            'content'           => 'new content from user1',
        ]);
    }

    public function test_with_invalid_values()
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com'])->object();

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $user1->getId(),
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