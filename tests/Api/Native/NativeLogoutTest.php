<?php

declare(strict_types=1);

namespace App\Tests\Api\Native;

use App\Entity\RefreshToken;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * That signing out needs no native endpoint of its own.
 *
 * `POST /api/token/invalidate` already serves a Bearer client: gesdinet's LogoutEventListener reads
 * the refresh token through the same chain extractor, which tries the body before the cookie, and
 * Lexik's BlockJWTListener blocklists the JWT through an extractor chain that now tries the
 * Authorization header first. The `delete_cookies` and `clear_site_data` directives on the firewall
 * are noise a native client discards. This is a test rather than a comment because none of it is
 * obvious from security.yaml, and the alternative is a third native endpoint nobody needs.
 *
 * disableReboot() because the blocklist is a cache.adapter.array under test, so it lives in the
 * container and would be thrown away between requests along with the kernel.
 */
#[ResetDatabase]
class NativeLogoutTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_native_session_is_ended_through_the_existing_endpoint(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->client->disableReboot();

        $this->client->jsonRequest('POST', '/api/native/login_check', [
            'username' => $user->username,
            'password' => 'password',
        ]);
        $this->assertResponseIsSuccessful();
        $session = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->client->jsonRequest(
            'POST',
            '/api/token/invalidate',
            ['refresh_token' => $session['refresh_token']],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $session['token']],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            'code' => 200,
            'message' => 'The supplied refresh_token has been invalidated.',
        ]);

        $entityManager = $this->entityManager();
        $entityManager->clear();
        $this->assertNull(
            $entityManager->getRepository(RefreshToken::class)->findOneBy(['refreshToken' => $session['refresh_token']])
        );

        // The JWT outlives the refresh token by up to an hour, so the session is only really over if
        // the blocklist caught the Bearer too. That is the half of this which depends on #1016: the
        // listeners find the JWT through the extractor chain, and a native client's only carrier is
        // the Authorization header.
        //
        // Asserted on the blocklist rather than by sending the token again, because a third request
        // could never show it. KernelBrowser defers terminate() for one request until the next one
        // starts, and ResetServicesListener resets cache.app there - an ArrayAdapter under test, so
        // the blocklist is wiped before the token is ever presented. AuthorizationHeaderTest covers
        // the refusal itself, in a single request where nothing has been reset.
        $blockedTokens = static::getContainer()->get('lexik_jwt_authentication.blocked_token_manager');
        $this->assertTrue($blockedTokens->has(JwtPayload::of($session['token'])));
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
