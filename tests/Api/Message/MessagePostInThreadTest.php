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

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class MessagePostInThreadTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $thread = MessageThreadFactory::new()->create();
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread'  => '/api/message_threads/' . $thread->id,
            'content' => 'content',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_post_message_in_thread(): void
    {
        $messageRepository = static::getContainer()->get(MessageRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $thread])->create();

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread'  => '/api/message_threads/' . $thread->id,
            'content' => 'new content from user1',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $messages = $messageRepository->findBy(['thread' => $thread]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $messages[0]->id,
            '@type' => 'Message',
            'id'                => $messages[0]->id,
            'creation_datetime' => $messages[0]->creationDatetime->format('c'),
            'author'            => [
                '@id' => '/api/users/' . $user1->id,
                '@type' => 'User',
                'id'       => $user1->id,
                'username' => 'base_user_1',
            ],
            'thread'            => [
                '@id' => '/api/message_threads/' . $thread->id,
                '@type' => 'MessageThread',
                'id' => $thread->id,
            ],
            'content'           => 'new content from user1',
        ]);
    }

    public function test_post_message_in_thread_but_not_in_participants(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);
        $user3 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_3', 'email' => 'base_user3@email.com']);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $thread])->create();

        $this->client->loginUser($user3);
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread'  => '/api/message_threads/' . $thread->id,
            'content' => 'new content from user1',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title'       => 'An error occurred',
            'description' => 'Thread not found.',
            'detail' => 'Thread not found.',
            'status' => 404,
            'type' => '/errors/404',
            '@context' => '/api/contexts/Error',
        ]);
    }

    public function test_post_message_in_thread_with_deleted_participant(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create([
            'username' => 'deleted_user',
            'email' => 'deleted@email.com',
            'deletionDatetime' => new \DateTimeImmutable(),
        ]);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $thread])->create();

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread'  => '/api/message_threads/' . $thread->id,
            'content' => 'message to deleted user',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_a9d3b7e1-4c6f-4e2a-8b5d-3f1c0e9a7d26',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'Vous ne pouvez pas envoyer un message à un utilisateur supprimé.',
            'description' => 'Vous ne pouvez pas envoyer un message à un utilisateur supprimé.',
            'violations' => [
                [
                    'propertyPath' => '',
                    'message' => 'Vous ne pouvez pas envoyer un message à un utilisateur supprimé.',
                    'code' => 'music_all_a9d3b7e1-4c6f-4e2a-8b5d-3f1c0e9a7d26',
                ],
            ],
            'status' => 422,
            'type' => '/validation_errors/music_all_a9d3b7e1-4c6f-4e2a-8b5d-3f1c0e9a7d26',
        ]);
    }

    public function test_post_message_with_content_too_long(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $thread])->create();

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread'  => '/api/message_threads/' . $thread->id,
            'content' => str_repeat('a', 5001),
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_with_invalid_values(): void
    {
        $thread = MessageThreadFactory::new()->create();
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread'  => '/api/message_threads/' . $thread->id,
            'content' => '',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'title'       => 'An error occurred',
            'description' => 'content: Cette valeur ne doit pas être vide.',
            'violations'        => [
                [
                    'propertyPath' => 'content',
                    'message'      => 'Cette valeur ne doit pas être vide.',
                    'code'         => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'status'            => 422,
            'detail'            => 'content: Cette valeur ne doit pas être vide.',
            'type'              => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@context' => '/api/contexts/ConstraintViolation',
        ]);
    }
}