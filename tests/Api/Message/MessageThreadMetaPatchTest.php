<?php

namespace App\Tests\Api\Message;

use App\Repository\Message\MessageThreadMetaRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
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
        $this->assertFalse($result->isRead);
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
            'id' => $meta->id
        ]);

        $result = $messageMetaRepository->find($meta->id);
        $this->assertTrue($result->isRead);
        $this->assertFalse($result->isDeleted);
    }
}