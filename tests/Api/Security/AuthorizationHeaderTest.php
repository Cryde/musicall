<?php

declare(strict_types=1);

namespace App\Tests\Api\Security;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * That `Authorization: Bearer` authenticates at all, which is what the native clients of #1016 rest
 * on and the only thing turning `authorization_header` back on buys.
 *
 * Minting the token here rather than signing in keeps this about the extractor: the native login
 * endpoints have tests of their own, and this one has to keep passing whatever they do.
 */
#[ResetDatabase]
class AuthorizationHeaderTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_bearer_token_authenticates_with_no_cookies_at_all(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        $this->client->request('GET', '/api/users/self', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtManager->create($user),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserSelf',
            '@id' => '/api/users/self',
            '@type' => 'UserSelf',
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'roles' => ['ROLE_USER'],
            'profile_picture' => null,
            'username_changed_datetime' => null,
            'has_password' => true,
        ]);
    }

    /**
     * Header and payload is the half of the split that script can read, so this is the only Bearer
     * an XSS payload could assemble. It carries no signature and must authenticate nothing, which is
     * the whole reason accepting the header alongside the cookies is safe for the web.
     */
    public function test_the_readable_half_of_a_split_jwt_is_not_a_bearer_token(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        [$header, $payload] = explode('.', $jwtManager->create($user));

        $this->client->request('GET', '/api/users/self', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $header . '.' . $payload,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Invalid JWT Token',
        ]);
    }

    /**
     * That the blocklist reaches a Bearer, which is what makes signing out of the app mean anything:
     * the refresh token is destroyed on logout, but the JWT would otherwise keep working for up to
     * an hour. NativeLogoutTest covers the other half, that logging out puts the token here.
     *
     * The blocklist is seeded directly rather than by logging out first, because it is a
     * cache.adapter.array under test and ResetServicesListener empties it between requests. This
     * test therefore makes exactly one.
     */
    public function test_a_blocklisted_jwt_is_refused_when_it_arrives_as_a_bearer(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $jwt = $jwtManager->create($user);

        static::getContainer()->get('lexik_jwt_authentication.blocked_token_manager')
            ->add($jwtManager->parse($jwt));

        $this->client->request('GET', '/api/users/self', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwt,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Invalid JWT Token',
        ]);
    }
}
