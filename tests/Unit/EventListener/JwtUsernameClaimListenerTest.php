<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\JwtUsernameClaimListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

class JwtUsernameClaimListenerTest extends TestCase
{
    public function test_the_username_is_added_next_to_the_id(): void
    {
        $user = new User();
        $user->id = 'user-id';
        $user->username = 'alice';
        $event = new JWTCreatedEvent(['roles' => ['ROLE_USER'], 'id' => 'user-id'], $user);

        new JwtUsernameClaimListener()($event);

        $this->assertSame(['roles' => ['ROLE_USER'], 'id' => 'user-id', 'username' => 'alice'], $event->getData());
    }

    public function test_a_token_for_another_kind_of_user_is_left_as_lexik_built_it(): void
    {
        $event = new JWTCreatedEvent(['roles' => [], 'id' => 'not-an-app-user'], new InMemoryUser('someone', null));

        new JwtUsernameClaimListener()($event);

        $this->assertSame(['roles' => [], 'id' => 'not-an-app-user'], $event->getData());
    }
}
