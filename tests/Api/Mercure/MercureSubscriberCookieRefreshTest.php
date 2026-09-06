<?php

declare(strict_types=1);

namespace App\Tests\Api\Mercure;

use App\Entity\RefreshToken;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Symfony\Component\HttpFoundation\Cookie;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * That `POST /api/token/refresh` renews the subscriber cookie is the load-bearing half of the claim
 * that one listener covers both ways a JWT is issued, and it rests on a vendor detail: gesdinet's
 * success handler delegates to Lexik's, which is the only thing that dispatches the event the
 * listener is on. If a bundle upgrade ever stops delegating, the cookie simply expires after an hour
 * and realtime dies with nothing in any log. Hence a test rather than a comment.
 *
 * `loginUser()` is no use here, since it authenticates one request and produces no refresh token, so
 * the token is seeded and put in the jar directly. It is stored unhashed by this bundle, and the
 * cookie has to be non-Secure or the test client will not replay it over plain HTTP.
 */
#[ResetDatabase]
class MercureSubscriberCookieRefreshTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_refreshing_the_jwt_renews_the_subscriber_cookie_for_the_same_user(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(RefreshToken::createForUserWithTtl('a-refresh-token', $user, 3600));
        $entityManager->flush();

        $this->client->getCookieJar()->set(new BrowserKitCookie('refresh_token', 'a-refresh-token', null, '/', self::HTTP_HOST));
        $this->client->request('POST', '/api/token/refresh');
        $this->assertResponseIsSuccessful();

        $this->assertResponseHasCookie('mercureAuthorization', '/.well-known/mercure');

        $cookie = null;
        foreach ($this->client->getResponse()->headers->getCookies() as $responseCookie) {
            if ($responseCookie->getName() === 'mercureAuthorization') {
                $cookie = $responseCookie;
            }
        }
        self::assertInstanceOf(Cookie::class, $cookie);

        // The same user's topic, not merely "a" cookie: a refresh that renewed somebody else's token
        // would be a far worse bug than one that renewed nothing.
        $this->assertSame(
            ['subscribe' => ['/users/' . $user->id . '/notifications']],
            JwtPayload::of($cookie->getValue())['mercure']
        );
    }
}
