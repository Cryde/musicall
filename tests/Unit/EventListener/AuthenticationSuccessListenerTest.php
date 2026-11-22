<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\EventListener\AuthenticationSuccessListener;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class AuthenticationSuccessListenerTest extends TestCase
{
    public function test_a_login_stamps_the_last_login(): void
    {
        $user = $this->member();

        $this->listenerHolding(new PostAuthenticationToken($user, 'login', []))
            ->onAuthenticationSuccessResponse(new AuthenticationSuccessEvent([], $user, new Response()));

        $this->assertNotNull($user->lastLoginDatetime);
    }

    public function test_a_token_refresh_leaves_the_last_login_alone(): void
    {
        $user = $this->member();
        $refreshToken = new PostRefreshTokenAuthenticationToken($user, 'api_token_refresh', [], RefreshToken::createForUserWithTtl('a-refresh-token', $user, 3600));

        $this->listenerHolding($refreshToken)
            ->onAuthenticationSuccessResponse(new AuthenticationSuccessEvent([], $user, new Response()));

        $this->assertNull($user->lastLoginDatetime);
    }

    public function test_a_user_that_is_not_a_member_is_left_alone(): void
    {
        $user = new class() implements UserInterface {
            public ?\DateTime $lastLoginDatetime = null;

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'someone';
            }
        };

        $this->listenerHolding(new PostAuthenticationToken($user, 'login', []))
            ->onAuthenticationSuccessResponse(new AuthenticationSuccessEvent([], $user, new Response()));

        $this->assertNull($user->lastLoginDatetime);
    }

    private function member(): User
    {
        $user = new User();
        $user->username = 'member';

        return $user;
    }

    private function listenerHolding(TokenInterface $token): AuthenticationSuccessListener
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        return new AuthenticationSuccessListener($tokenStorage);
    }
}
