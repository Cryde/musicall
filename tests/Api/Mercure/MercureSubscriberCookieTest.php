<?php

declare(strict_types=1);

namespace App\Tests\Api\Mercure;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class MercureSubscriberCookieTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string COOKIE_NAME = '__Secure-mercure_access_token';
    private const string COOKIE_PATH = '/.well-known/mercure';

    public function test_login_issues_a_subscriber_token_for_that_user_and_nothing_else(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user->username,
            'password' => 'password',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertResponseHasCookie(self::COOKIE_NAME, self::COOKIE_PATH);

        $cookie = $this->responseCookie(self::COOKIE_NAME);
        $this->assertTrue($cookie->isHttpOnly(), 'A token the page never reads must not be scriptable.');
        $this->assertTrue($cookie->isSecure());
        $this->assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());

        // The whole grant, not a subset: the hub decides what this browser may read from exactly
        // this, and an extra entry here is an extra permission. In particular there is no "publish".
        // Mercure 1.0's shape (#1153): an RFC 9396 authorization detail rather than a `mercure` claim.
        $payload = JwtPayload::of($cookie->getValue());
        $this->assertSame([
            [
                'type' => 'https://mercure.rocks/authorization-detail',
                'actions' => ['subscribe'],
                'topics' => [['match' => '/users/' . $user->id . '/notifications']],
            ],
        ], $payload['authorization_details']);
        $this->assertArrayNotHasKey('mercure', $payload);
        // Who issued it and for which hub, the two the hub checks against its issuer block and its
        // pinned resource identifier.
        $this->assertSame(
            ['iss' => 'https://musicall.local', 'aud' => 'https://musicall.local/.well-known/mercure', 'sub' => 'musicall', 'client_id' => 'musicall'],
            ['iss' => $payload['iss'], 'aud' => $payload['aud'], 'sub' => $payload['sub'], 'client_id' => $payload['client_id']],
        );
    }

    public function test_the_subscriber_token_expires_with_the_jwt_it_came_with(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user->username,
            'password' => 'password',
        ]);
        $this->assertResponseIsSuccessful();

        // Both live one hour, so the refresh the frontend already performs renews the pair. A
        // subscriber token outliving its JWT would keep an open stream for a logged-out browser.
        $this->assertEqualsWithDelta(
            time() + 3600,
            $this->responseCookie(self::COOKIE_NAME)->getExpiresTime(),
            5
        );
    }

    public function test_logout_clears_the_subscriber_token(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/token/invalidate');

        // A real browser reaches here with a refresh_token cookie and gets a 200. loginUser() cannot
        // produce one, and the cookie a real login would set is Secure, which the test client never
        // replays over plain HTTP. The 400 is gesdinet's answer to that, and it comes after the
        // clearing this test is about: CookieClearingLogoutListener runs at priority -255 on all
        // three of gesdinet's branches alike, so the 400 path exercises the identical mechanism.
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            'code' => 400,
            'message' => 'No refresh_token found.',
        ]);

        // Cleared on its own path, or the browser keeps the old one and the next person on this
        // machine can still read the previous account's topic until the token expires.
        $cookie = $this->responseCookie(self::COOKIE_NAME);
        $this->assertSame(self::COOKIE_PATH, $cookie->getPath());
        $this->assertTrue($cookie->isCleared());
    }

    public function test_deleting_an_account_clears_the_subscriber_token(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/delete_account', [
            'password' => 'password',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $cookie = $this->responseCookie(self::COOKIE_NAME);
        $this->assertSame(self::COOKIE_PATH, $cookie->getPath());
        $this->assertTrue($cookie->isCleared());
    }

    private function responseCookie(string $name): Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        self::fail(sprintf('The response set no "%s" cookie.', $name));
    }
}
