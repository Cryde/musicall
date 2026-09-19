<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Observability;

use App\Entity\User;
use App\Security\Observability\AuthContextBuilder;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ChainExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestBodyExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestCookieExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthContextBuilderTest extends TestCase
{
    /**
     * The half present pair is one of the hypotheses under test, so the three cookies are reported
     * separately. `jwt_hp` follows the token TTL while `jwt_s` is a browser session cookie, so this
     * state is what an ordinary returning visitor actually presents, and a single "cookies: true"
     * would make it indistinguishable from a complete pair.
     */
    public function test_a_half_present_cookie_pair_is_visible_as_such(): void
    {
        $request = Request::create('/api/users/self');
        $request->cookies->set('jwt_s', 'a-signature');

        $context = $this->builderFor($request)->build();

        $this->assertSame(
            ['jwt_hp' => false, 'jwt_s' => true, 'refresh_token' => false],
            $context['cookies_present']
        );
        $this->assertSame('cookie', $context['client_kind']);
    }

    public function test_a_bearer_caller_is_told_apart_from_a_browser(): void
    {
        $request = Request::create('/api/users/self');
        $request->headers->set('Authorization', 'Bearer a.jwt.here');

        $this->assertSame('authorization_header', $this->builderFor($request)->build()['client_kind']);
    }

    /**
     * The native surface sends its refresh token in the body, which is what separates it from the
     * web in every query built on this data.
     */
    public function test_a_token_in_the_body_is_reported_as_body(): void
    {
        $request = Request::create('/api/native/token/refresh', 'POST', [], [], [], [], json_encode(['refresh_token' => 'a-refresh-token']));

        $context = $this->builderFor($request)->build();

        $this->assertSame('body', $context['client_kind']);
        $this->assertSame(substr(hash('sha256', 'a-refresh-token'), 0, 16), $context['token_hash']);
    }

    /**
     * Non-negotiable: the raw token never appears, anywhere, at any level.
     */
    public function test_the_raw_token_is_never_in_the_context(): void
    {
        $request = Request::create('/api/token/refresh', 'POST');
        $request->cookies->set('refresh_token', 'the-secret-token-value');

        $context = $this->builderFor($request)->build();

        $this->assertStringNotContainsString('the-secret-token-value', json_encode($context, JSON_THROW_ON_ERROR));
        $this->assertSame(16, strlen((string) $context['token_hash']));
    }

    /**
     * The account is named by its internal id. An email or a username in here would be the sort of
     * thing nobody notices until it is a year of retained logs.
     */
    public function test_the_account_is_named_by_id_and_nothing_else(): void
    {
        $user = new User();
        $user->id = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';
        $user->username = 'a-username';
        $user->email = 'someone@example.com';

        $context = $this->builderFor(Request::create('/api/token/refresh'))->build($user);
        $encoded = json_encode($context, JSON_THROW_ON_ERROR);

        $this->assertSame('0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b', $context['user_ref']);
        $this->assertStringNotContainsString('a-username', $encoded);
        $this->assertStringNotContainsString('someone@example.com', $encoded);
    }

    /**
     * A User that never came from Doctrine has an uninitialised typed id, and reading one throws.
     */
    public function test_a_user_without_an_id_does_not_blow_up(): void
    {
        $this->assertNull($this->builderFor(Request::create('/'))->build(new User())['user_ref']);
    }

    /**
     * One id per request, so the events a single refresh emits read as one story.
     */
    public function test_the_request_id_is_stable_within_a_request(): void
    {
        $builder = $this->builderFor(Request::create('/api/token/refresh'));

        $this->assertSame($builder->build()['request_id'], $builder->build()['request_id']);
    }

    private function builderFor(Request $request): AuthContextBuilder
    {
        $extractor = new ChainExtractor();
        $extractor->addExtractor(new RequestCookieExtractor());
        $extractor->addExtractor(new RequestBodyExtractor());

        $stack = new RequestStack();
        $stack->push($request);

        return new AuthContextBuilder($stack, $extractor, 'refresh_token');
    }
}
