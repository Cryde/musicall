<?php

declare(strict_types=1);

namespace App\Tests\Api\Native;

use App\Entity\RefreshToken;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * POST /api/native/token/refresh (#1016), and the boundary that makes a second issuing surface safe
 * rather than a second way in.
 *
 * `loginUser()` is no use here: it produces no refresh token and authenticates one request only. The
 * token is seeded directly, as MercureSubscriberCookieRefreshTest already does, and the cookie has
 * to be non-Secure or the test client will not replay it over plain HTTP.
 */
#[ResetDatabase]
class NativeTokenRefreshTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_token_sent_in_the_body_renews_the_session_into_the_body(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->seedRefreshToken('a-refresh-token', $user);

        $this->client->jsonRequest('POST', '/api/native/token/refresh', [
            'refresh_token' => 'a-refresh-token',
        ]);

        $this->assertResponseIsSuccessful();

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $keys = array_keys($body);
        sort($keys);
        $this->assertSame(['mercure_authorization', 'refresh_token', 'token'], $keys);

        $this->assertSame($user->username, JwtPayload::of($body['token'])['username']);
        $this->assertSame(
            ['subscribe' => ['/users/' . $user->id . '/notifications']],
            JwtPayload::of($body['mercure_authorization'])['mercure']
        );

        // single_use: true, so the token that was spent is gone and the one in the body is its
        // replacement. Getting this wrong strands the client on a token the server has discarded.
        $this->assertNotSame('a-refresh-token', $body['refresh_token']);
        $this->assertNull($this->findRefreshToken('a-refresh-token'));
        $this->assertNotNull($this->findRefreshToken($body['refresh_token']));

        $this->assertSame([], $this->client->getResponse()->headers->getCookies());
        $this->assertResponseHeaderSame('Cache-Control', 'no-store, private');
    }

    /**
     * The whole security argument for #1016, as one test.
     *
     * An injected script cannot read the httpOnly refresh_token cookie, but it can make the browser
     * send it: a same-origin POST carries it by itself. If this endpoint read cookies, that script
     * would receive a complete JWT in the response body and the split cookies would have been
     * pointless. So the request has to be refused, and it has to be refused *without the token
     * having been spent* - a rotated row would mean the cookie was read and merely not returned,
     * which is a different and much worse thing.
     */
    public function test_a_refresh_token_arriving_as_a_cookie_is_not_even_read(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->seedRefreshToken('a-refresh-token', $user);

        $this->client->getCookieJar()->set(
            new BrowserKitCookie('refresh_token', 'a-refresh-token', null, '/', self::HTTP_HOST)
        );
        $this->client->request('POST', '/api/native/token/refresh');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Missing JWT Refresh Token',
        ]);
        $this->assertNotNull($this->findRefreshToken('a-refresh-token'));
    }

    /**
     * The other side of that boundary, so the pair reads as one statement: the very same cookie the
     * native endpoint refuses is how the web has always refreshed, and still is. Blinding is scoped
     * to NativeSurface::PREFIX and must not have leaked.
     */
    public function test_the_same_cookie_still_refreshes_on_the_web_endpoint(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->seedRefreshToken('a-refresh-token', $user);

        $this->client->getCookieJar()->set(
            new BrowserKitCookie('refresh_token', 'a-refresh-token', null, '/', self::HTTP_HOST)
        );
        $this->client->request('POST', '/api/token/refresh');

        // 204 exactly: the web's body is stripped because cookies were set, and a body appearing
        // here would mean the global remove_token_from_body_when_cookies_used had been flipped.
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertResponseHasCookie('jwt_hp');
        $this->assertResponseHasCookie('jwt_s');
        $this->assertResponseHasCookie('refresh_token');
    }

    /**
     * gesdinet's RefreshTokenAuthenticator::supports() is a path check with no method check, and
     * SameSite=Lax does send cookies on a cross-site top-level navigation, so without this a bare
     * `window.location` from any site would have rendered a fresh credential pair into the victim's
     * window on our own origin. Blinding already makes it useless; the router refuses it outright.
     */
    public function test_a_navigation_to_the_refresh_endpoint_is_refused_by_the_router(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->seedRefreshToken('a-refresh-token', $user);

        $this->client->getCookieJar()->set(
            new BrowserKitCookie('refresh_token', 'a-refresh-token', null, '/', self::HTTP_HOST)
        );
        $this->client->request('GET', '/api/native/token/refresh');

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        $this->assertNotNull($this->findRefreshToken('a-refresh-token'));
    }

    private function seedRefreshToken(string $value, object $user): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist(RefreshToken::createForUserWithTtl($value, $user, 3600));
        $entityManager->flush();
    }

    private function findRefreshToken(string $value): ?RefreshToken
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(RefreshToken::class)->findOneBy(['refreshToken' => $value]);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
