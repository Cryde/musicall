<?php

namespace App\Tests\Api\Message;

use App\Repository\Message\MessageThreadMetaRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class MessageThreadMetaPatchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $meta = MessageThreadMetaFactory::new(['user' => $user1])->create();

        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $meta->id, [
            'is_read'    => true,
            'is_deleted' => true, // shouldn't change anything
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_patch_message_thread_meta(): void
    {
        $messageMetaRepository = static::getContainer()->get(MessageThreadMetaRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $thread = MessageThreadFactory::new()->create();
        $meta = MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();

        // pretest
        $result = $messageMetaRepository->find($meta->id);
        $this->assertNull($result->lastReadDatetime);
        $this->assertFalse($result->isDeleted);

        $this->client->loginUser($user1);
        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $meta->id, [
            'is_read' => true,
            'is_deleted' => true // shouldn't change anything
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT'=>'application/ld+json']);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas/' . $meta->id,
            '@type' => 'MessageThreadMeta',
            'id' => $meta->id,
            // The response carries the new count so the client does not have to guess it.
            'unread_count' => 0,
        ]);

        $result = $messageMetaRepository->find($meta->id);
        $this->assertNotNull($result->lastReadDatetime);
        $this->assertFalse($result->isDeleted);
    }

    public function test_patch_without_is_read_is_a_validation_error(): void
    {
        // Nothing populates isRead on the read side any more, so an omitted key leaves a typed
        // property uninitialised. Reading that raises an Error, not an exception, which no handler
        // turns into a 4xx: this used to be a 500. The constraint decides it before the processor.
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $meta = MessageThreadMetaFactory::new(['user' => $user1])->create();

        $this->client->loginUser($user1);
        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $meta->id, [
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'is_read',
                    'message' => 'Veuillez indiquer si le fil est lu',
                    'code' => 'ad32d13f-c3d4-423b-909a-857b961eb720',
                ],
            ],
            'detail' => 'is_read: Veuillez indiquer si le fil est lu',
            'type' => '/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            'title' => 'An error occurred',
            'description' => 'is_read: Veuillez indiquer si le fil est lu',
        ]);
    }

    public function test_patch_is_read_false_puts_the_position_back_to_nothing(): void
    {
        $messageMetaRepository = static::getContainer()->get(MessageThreadMetaRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $meta = MessageThreadMetaFactory::new([
            'user' => $user1,
            'lastReadDatetime' => new \DateTimeImmutable('2026-09-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user1);
        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $meta->id, [
            'is_read' => false,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertNull($messageMetaRepository->find($meta->id)->lastReadDatetime);
    }
}
