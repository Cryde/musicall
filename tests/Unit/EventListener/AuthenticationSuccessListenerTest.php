<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\AuthenticationSuccessListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticationSuccessListenerTest extends TestCase
{
    public function test_on_authentication_success_response(): void
    {
        $listener = new AuthenticationSuccessListener();

        $user = new User();
        $user->setLastLoginDatetime(null);

        $this->assertNull($user->getLastLoginDatetime());
        $listener->onAuthenticationSuccessResponse(new AuthenticationSuccessEvent([], $user, new Response()));
        $this->assertNotNull($user->getLastLoginDatetime());

        // this is a fake user representation
        $user1 = new class() implements UserInterface {
            private $lastLoginDatetime;

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }
            public function getUserIdentifier(): string
            {
                return '124';
            }
            public function getLastLoginDatetime()
            {
                return $this->lastLoginDatetime;
            }
        };

        $this->assertNull($user1->getLastLoginDatetime());
        $listener->onAuthenticationSuccessResponse(new AuthenticationSuccessEvent([], $user1, new Response()));
        $this->assertNull($user1->getLastLoginDatetime());
    }
}