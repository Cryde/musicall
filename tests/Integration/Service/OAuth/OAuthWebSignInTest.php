<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\OAuth;

use App\Service\OAuth\OAuthWebSignIn;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The end of every social sign-in on the site, Google's included (#1271), whose callback has no API
 * test of its own because it runs through KnpU. DEFAULT_URI is `http://localhost` in .env.test.
 */
#[ResetDatabase]
class OAuthWebSignInTest extends KernelTestCase
{
    /** @return iterable<string, array{?string, string}> */
    public static function returnUrls(): iterable
    {
        yield 'none' => [null, 'http://localhost'];
        yield 'empty' => ['', 'http://localhost'];
        yield 'a page of the site' => ['/band-space/abc?tab=files', 'http://localhost/band-space/abc?tab=files'];
        yield 'the site, absolute' => ['http://localhost/forum', 'http://localhost/forum'];
        yield 'another site' => ['https://evil.example/phish', 'http://localhost'];
        yield 'protocol relative' => ['//evil.example', 'http://localhost'];
        yield 'a backslash trick' => ['/\\evil.example', 'http://localhost'];
    }

    #[DataProvider('returnUrls')]
    public function test_a_return_url_is_followed_only_within_the_site(?string $returnUrl, string $expected): void
    {
        $this->assertSame($expected, $this->webSignIn()->redirectUrlFor($returnUrl));
    }

    public function test_a_failure_lands_on_the_login_page_with_its_key(): void
    {
        $this->assertSame('http://localhost/login?oauth_error=email_exists', $this->webSignIn()->fail('email_exists')->getTargetUrl());
    }

    public function test_a_signed_in_user_gets_the_session_cookies(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums']);

        $response = $this->webSignIn()->complete($user, 'http://localhost/forum', Request::create('https://musicall.test/oauth/google/callback'));

        $this->assertSame('http://localhost/forum', $response->getTargetUrl());
        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = [$cookie->isHttpOnly(), $cookie->isSecure(), $cookie->getSameSite()];
        }
        ksort($cookies);
        $this->assertSame([
            '__Secure-mercure_access_token' => [true, true, 'strict'],
            'jwt_hp' => [false, true, 'strict'],
            'jwt_s' => [true, true, 'strict'],
            'refresh_token' => [true, true, 'lax'],
        ], $cookies);
    }

    /** These cookies are minted by hand, so no user checker runs: the suspension is checked here. */
    public function test_a_suspended_user_is_refused_and_given_no_session(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['suspensionDatetime' => new \DateTimeImmutable('2026-10-01')]);

        $response = $this->webSignIn()->complete($user, 'http://localhost/forum', Request::create('https://musicall.test/oauth/google/callback'));

        $this->assertSame('http://localhost/login?oauth_error=account_suspended', $response->getTargetUrl());
        $this->assertSame([], $response->headers->getCookies());
    }

    private function webSignIn(): OAuthWebSignIn
    {
        return self::getContainer()->get(OAuthWebSignIn::class);
    }
}
