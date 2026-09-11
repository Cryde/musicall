<?php

namespace App\Tests\Api\Message;

use App\Repository\Message\MessageThreadMetaRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
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
        // Asserted in full so it stays byte for byte the same answer as the nonexistent id below.
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
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

    public function test_you_cannot_patch_somebody_elses_thread_meta(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@email.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@email.com']);
        $meta = MessageThreadMetaFactory::new(['user' => $owner])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $meta->id, [
            'is_read' => true,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        // 404 rather than 403, matching MessageThreadItemProvider: a 403 confirms the row exists.
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'description' => 'Message thread meta introuvable',
            'detail' => 'Message thread meta introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_somebody_elses_thread_meta_is_indistinguishable_from_one_that_does_not_exist(): void
    {
        // The ownership check has to run before validation, not after. It used to live in the
        // processor, which runs last, so a body that failed validation against somebody else's row
        // came back 422 while a row that did not exist came back 404: an existence oracle for any id.
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@email.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@email.com']);
        $meta = MessageThreadMetaFactory::new(['user' => $owner])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $meta->id, [
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'description' => 'Message thread meta introuvable',
            'detail' => 'Message thread meta introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function idsThatAreNotUuids(): iterable
    {
        yield 'not a uuid at all' => ['not-a-uuid'];
        // Thirty six characters of hex with no hyphens. It is the shape a looser
        // `[0-9a-fA-F\-]{36}` requirement would wave through, and Ramsey rejects it, so Doctrine
        // would throw converting it. Requirement::UUID pins the hyphens, the version and the variant.
        yield 'hex of the right length but not a uuid' => [str_repeat('a', 36)];
        yield 'the nil uuid' => ['00000000-0000-0000-0000-000000000000'];
    }

    #[DataProvider('idsThatAreNotUuids')]
    public function test_an_id_that_is_not_a_uuid_is_not_a_server_error(string $id): void
    {
        // The column is a uuid, so an unconvertible value reached Doctrine and threw where nothing
        // catches it: a 500 on a route any signed in person can reach. The route requirement now
        // refuses to match, so the request never reaches the provider at all.
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $id, [
            'is_read' => true,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        // The requirement is declared on the resource rather than on the operation, and that is what
        // makes this a 404. API Platform also generates an item GET route here so it can build `@id`
        // IRIs; declared per operation, that shadow route kept no requirement, still matched, and the
        // router answered 405 with a misleading `Allow: GET`. Declared on the resource, neither route
        // matches and the answer is the same 404 as every other rejection on this endpoint.
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_anonymous_cannot_tell_an_id_that_exists_from_one_that_does_not(): void
    {
        // The other half of the oracle, and the half that needed no account at all: before the
        // operation declared its own gate, an id that existed reached the processor and came back 401
        // while one that did not stopped at the provider and came back 404.
        UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);

        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/11111111-2222-4333-8444-555555555555', [
            'is_read' => true,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }
}
