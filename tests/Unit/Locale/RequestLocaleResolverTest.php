<?php

declare(strict_types=1);

namespace App\Tests\Unit\Locale;

use App\Entity\User;
use App\Locale\RequestLocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RequestLocaleResolverTest extends TestCase
{
    private RequestLocaleResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RequestLocaleResolver(['fr', 'en'], 'fr');
    }

    public function test_the_user_locale_wins_over_the_cookie_and_the_header(): void
    {
        $request = $this->request(cookie: 'fr', acceptLanguage: 'fr');

        self::assertSame('en', $this->resolver->resolve($request, $this->user('en')));
    }

    public function test_an_unsupported_user_locale_falls_through_to_the_cookie(): void
    {
        $request = $this->request(cookie: 'en', acceptLanguage: 'fr');

        self::assertSame('en', $this->resolver->resolve($request, $this->user('de')));
    }

    public function test_the_cookie_wins_over_the_header_for_an_anonymous_request(): void
    {
        $request = $this->request(cookie: 'en', acceptLanguage: 'fr');

        self::assertSame('en', $this->resolver->resolve($request, null));
    }

    public function test_an_unsupported_cookie_falls_through_to_the_header(): void
    {
        $request = $this->request(cookie: 'de', acceptLanguage: 'en');

        self::assertSame('en', $this->resolver->resolve($request, null));
    }

    public function test_the_header_is_read_in_preference_order_with_a_region_matching_its_language(): void
    {
        $request = $this->request(acceptLanguage: 'de-DE, en-US;q=0.8, fr;q=0.5');

        self::assertSame('en', $this->resolver->resolve($request, null));
    }

    public function test_an_unsupported_header_falls_back_to_the_default(): void
    {
        $request = $this->request(acceptLanguage: 'de-DE, es;q=0.8');

        self::assertSame('fr', $this->resolver->resolve($request, null));
    }

    public function test_nothing_at_all_falls_back_to_the_default(): void
    {
        self::assertSame('fr', $this->resolver->resolve($this->request(), null));
    }

    public function test_the_default_wins_even_when_it_is_not_listed_first(): void
    {
        $resolver = new RequestLocaleResolver(['en', 'fr'], 'fr');

        self::assertSame('fr', $resolver->resolve($this->request(acceptLanguage: 'de'), null));
    }

    public function test_only_french_is_reachable_while_french_is_the_only_enabled_locale(): void
    {
        $resolver = new RequestLocaleResolver(['fr'], 'fr');
        $request = $this->request(cookie: 'en', acceptLanguage: 'en');

        self::assertSame('fr', $resolver->resolve($request, $this->user('en')));
    }

    public function test_an_array_cookie_is_ignored_rather_than_refused(): void
    {
        $request = Request::create('/api/users/self', cookies: [RequestLocaleResolver::COOKIE => ['en']]);
        $request->headers->set('Accept-Language', 'en');

        self::assertSame('en', $this->resolver->resolve($request, null));
    }

    public function test_an_oversized_header_is_not_parsed(): void
    {
        $request = $this->request(acceptLanguage: str_repeat('en;q=0.5,', 100));

        self::assertSame('fr', $this->resolver->resolve($request, null));
    }

    private function request(?string $cookie = null, ?string $acceptLanguage = null): Request
    {
        $request = Request::create('/api/users/self', cookies: $cookie !== null ? [RequestLocaleResolver::COOKIE => $cookie] : []);
        // Request::create() sends `en-us,en;q=0.5` unless told otherwise.
        $request->headers->remove('Accept-Language');
        if ($acceptLanguage !== null) {
            $request->headers->set('Accept-Language', $acceptLanguage);
        }

        return $request;
    }

    private function user(string $locale): User
    {
        $user = new User();
        $user->locale = $locale;

        return $user;
    }
}
