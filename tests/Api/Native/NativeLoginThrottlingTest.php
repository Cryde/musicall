<?php

declare(strict_types=1);

namespace App\Tests\Api\Native;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * That the native login draws on the web firewall's password-guessing budget rather than one of its
 * own.
 *
 * LoginThrottlingFactory puts the firewall name into the limiter id, so a `login_throttling` block
 * written out on native_login would have created a second, independent allowance, and an attacker
 * alternating the two endpoints would get twice the attempts against one account. security.yaml
 * therefore names the web firewall's limiter service explicitly; this is what says so.
 *
 * The budget is burned through the service rather than by sending five requests, because the
 * limiter's pool is a cache.adapter.array under test and ResetServicesListener empties it between
 * requests. Burning it before the first request works: KernelBrowser has nothing to terminate yet.
 */
#[ResetDatabase]
class NativeLoginThrottlingTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_native_login_shares_the_web_firewall_s_limiter(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        // DefaultLoginRateLimiter keys the local limiter on username plus client IP, both read off
        // a Request, so the budget is spent against one shaped like the one the client will send.
        $attempt = Request::create('/api/login_check', 'POST');
        $attempt->attributes->set(SecurityRequestAttributes::LAST_USERNAME, $user->username);

        $limiter = static::getContainer()->get('security.login_throttling.login.limiter');
        for ($i = 0; $i < 5; ++$i) {
            $limiter->consume($attempt);
        }

        $this->client->jsonRequest('POST', '/api/native/login_check', [
            'username' => $user->username,
            'password' => 'bad',
        ]);

        // Refused for being too frequent, not for the password: had the native firewall its own
        // limiter, this would be the ordinary "Identifiants invalides." of a wrong password.
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Plusieurs tentatives de connexion ont échoué, veuillez réessayer dans 1 minute.',
        ]);
    }
}
