<?php

declare(strict_types=1);

namespace App\Tests\Api\Native;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * POST /api/native/login_check, the endpoint that exists so a native client does not have to
 * pretend to be a browser (#1016).
 *
 * The bodies carry freshly minted JWTs, so the full-body assertion the project asks for lands on the
 * error responses, and the successful one is pinned by its exact key set plus the claims inside each
 * token. A field appearing or disappearing fails here either way.
 */
#[ResetDatabase]
class NativeLoginTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_it_returns_the_whole_session_in_the_body_and_sets_no_cookies(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/native/login_check', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertResponseIsSuccessful();

        // Sorted, because the order the fields land in follows ResponseHeaderBag's cookie bag,
        // which is keyed by path: mercureAuthorization lives on /.well-known/mercure and so comes
        // out first. What is being pinned is the set, and that nothing else joins it.
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $keys = array_keys($body);
        sort($keys);
        $this->assertSame(['mercure_authorization', 'refresh_token', 'token'], $keys);

        // A complete JWT, not the readable half the web's jwt_hp cookie carries.
        $this->assertCount(3, explode('.', $body['token']));
        $this->assertSame($user->username, JwtPayload::of($body['token'])['username']);

        // The same user's topic, and only a subscribe grant. A native client reaches the hub with
        // this in an Authorization header, where the web needs it as a cookie because an EventSource
        // cannot set one.
        $this->assertSame(
            ['subscribe' => ['/users/' . $user->id . '/notifications']],
            JwtPayload::of($body['mercure_authorization'])['mercure']
        );

        // The point of the endpoint. Anything here would be a credential the client never asked for
        // and a jar it would have to keep.
        $this->assertSame([], $this->client->getResponse()->headers->getCookies());
    }

    /**
     * The web's credentials travel in Set-Cookie, which is never cached. These travel in a body.
     */
    public function test_the_response_is_not_cacheable(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/native/login_check', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Cache-Control', 'no-store, private');
    }

    /**
     * Same failure handler as the web, so an account that exists but is not verified still gives
     * nothing away to a wrong password.
     */
    public function test_a_wrong_password_is_refused_exactly_as_on_the_web(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/native/login_check', [
            'username' => $user->username,
            'password' => 'bad',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Identifiants invalides.',
        ]);
        $this->assertSame([], $this->client->getResponse()->headers->getCookies());
    }

    /**
     * That App\Security\UserChecker and App\Security\AuthenticationFailureHandler are on this
     * firewall too, which is what lets the app route someone to the verification flow.
     */
    public function test_an_unverified_account_is_told_so_after_a_correct_password(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['confirmationDatetime' => null]);

        $this->client->jsonRequest('POST', '/api/native/login_check', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'account_not_verified',
            'email' => $user->email,
        ]);
    }

    /**
     * The point of the whole change: what comes out of here is usable on the rest of the API with
     * nothing but a header, so the app needs no cookie jar and can keep the token in memory.
     */
    public function test_the_token_it_returns_authenticates_the_rest_of_the_api(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/native/login_check', [
            'username' => $user->username,
            'password' => 'password',
        ]);
        $this->assertResponseIsSuccessful();
        $session = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->client->request('GET', '/api/users/self', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $session['token'],
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
     * gesdinet's authenticator matches on the path alone, and SameSite=Lax sends cookies on a
     * cross-site top-level navigation. The blinding listener makes that harmless; the router refuses
     * it one layer earlier.
     */
    public function test_it_answers_nothing_to_a_navigation(): void
    {
        $this->client->request('GET', '/api/native/login_check');

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
