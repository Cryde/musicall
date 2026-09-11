<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class MessageThreadGetTest extends ApiTestCase
{
    public function test_an_id_that_is_not_a_uuid_is_not_a_server_error(): void
    {
        // The id reached Doctrine, which cannot convert it to a uuid and throws where nothing catches
        // it: a 500 on a route any signed in person can reach. Requirement::UUID on the operation stops
        // the route matching at all, and unlike the sibling PATCH there is no other route on this path
        // to fall through to, so this one is a plain 404.
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/message_threads/not-a-uuid', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
