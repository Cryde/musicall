<?php

declare(strict_types=1);

namespace App\Tests\Unit\Locale;

use App\Entity\User;
use App\Locale\LocalizedPath;
use App\Locale\RequestLocaleResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RequestLocaleResolverTest extends TestCase
{
    private RequestLocaleResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = self::resolver(['fr', 'en']);
    }

    public function test_the_front_end_locale_wins_over_the_user_the_cookie_and_accept_language(): void
    {
        $request = self::request(cookie: 'fr', acceptLanguage: 'fr', frontEndLocale: 'en');

        self::assertSame('en', $this->resolver->resolve($request, $this->user('fr')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function frontEndLocalesThatAreNotAnEnabledLocale(): iterable
    {
        yield 'not enabled' => ['de'];
        yield 'another case' => ['EN'];
        yield 'with a region' => ['en-US'];
        yield 'sent twice, as PHP joins it' => ['en, fr'];
        yield 'empty' => [''];
        yield 'oversized' => [str_repeat('en', 10000)];
    }

    #[DataProvider('frontEndLocalesThatAreNotAnEnabledLocale')]
    public function test_a_front_end_locale_that_is_not_exactly_an_enabled_locale_falls_through_to_the_user(string $frontEndLocale): void
    {
        $request = self::request(acceptLanguage: 'fr', frontEndLocale: $frontEndLocale);

        self::assertSame('en', $this->resolver->resolve($request, $this->user('en')));
    }

    public function test_a_page_under_a_locale_prefix_wins_over_the_user_the_cookie_and_accept_language(): void
    {
        $request = self::request(cookie: 'fr', acceptLanguage: 'fr', path: '/en/publications');

        self::assertSame('en', $this->resolver->resolve($request, $this->user('fr')));
        self::assertFalse($request->attributes->has('_vary_by_language'));
    }

    public function test_the_front_end_locale_wins_over_the_page_prefix(): void
    {
        $request = self::request(frontEndLocale: 'fr', path: '/en/publications');

        self::assertSame('fr', $this->resolver->resolve($request, null));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pathsWithoutALocalePrefix(): iterable
    {
        yield 'a French page' => ['/publications'];
        yield 'the home page' => ['/'];
        yield 'a segment starting like a locale' => ['/enfants'];
        yield 'the default locale as a prefix' => ['/fr/publications'];
        yield 'a locale that is not enabled' => ['/de/publications'];
        yield 'an API call' => ['/api/users/self'];
    }

    #[DataProvider('pathsWithoutALocalePrefix')]
    public function test_a_path_without_an_enabled_locale_prefix_falls_through_to_the_user(string $path): void
    {
        $request = self::request(acceptLanguage: 'fr', path: $path);

        self::assertSame('en', $this->resolver->resolve($request, $this->user('en')));
    }

    public function test_the_prefix_is_ignored_while_french_is_the_only_enabled_locale(): void
    {
        $request = self::request(acceptLanguage: 'fr', path: '/en/publications');

        self::assertSame('fr', self::resolver(['fr'])->resolve($request, null));
    }

    public function test_the_user_locale_wins_over_the_cookie_and_accept_language(): void
    {
        $request = self::request(cookie: 'fr', acceptLanguage: 'fr');

        self::assertSame('en', $this->resolver->resolve($request, $this->user('en')));
    }

    public function test_an_unsupported_user_locale_falls_through_to_the_cookie(): void
    {
        $request = self::request(cookie: 'en', acceptLanguage: 'fr');

        self::assertSame('en', $this->resolver->resolve($request, $this->user('de')));
    }

    public function test_the_cookie_wins_over_accept_language_for_an_anonymous_request(): void
    {
        $request = self::request(cookie: 'en', acceptLanguage: 'fr');

        self::assertSame('en', $this->resolver->resolve($request, null));
    }

    public function test_an_unsupported_cookie_falls_through_to_accept_language(): void
    {
        $request = self::request(cookie: 'de', acceptLanguage: 'en');

        self::assertSame('en', $this->resolver->resolve($request, null));
    }

    public function test_accept_language_is_read_in_preference_order_with_a_region_matching_its_language(): void
    {
        $request = self::request(acceptLanguage: 'de-DE, en-US;q=0.8, fr;q=0.5');

        self::assertSame('en', $this->resolver->resolve($request, null));
    }

    public function test_an_unsupported_accept_language_falls_back_to_the_default(): void
    {
        $request = self::request(acceptLanguage: 'de-DE, es;q=0.8');

        self::assertSame('fr', $this->resolver->resolve($request, null));
    }

    public function test_nothing_at_all_falls_back_to_the_default(): void
    {
        self::assertSame('fr', $this->resolver->resolve(self::request(), null));
    }

    public function test_the_default_wins_even_when_it_is_not_listed_first(): void
    {
        $resolver = self::resolver(['en', 'fr']);

        self::assertSame('fr', $resolver->resolve(self::request(acceptLanguage: 'de'), null));
    }

    public function test_only_french_is_reachable_while_french_is_the_only_enabled_locale(): void
    {
        $resolver = self::resolver(['fr']);
        $request = self::request(cookie: 'en', acceptLanguage: 'en', frontEndLocale: 'en');

        self::assertSame('fr', $resolver->resolve($request, $this->user('en')));
    }

    public function test_an_array_cookie_is_ignored_rather_than_refused(): void
    {
        $request = Request::create('/api/users/self', cookies: [RequestLocaleResolver::COOKIE => ['en']]);
        $request->headers->set('Accept-Language', 'en');

        self::assertSame('en', $this->resolver->resolve($request, null));
    }

    public function test_an_oversized_accept_language_is_not_parsed(): void
    {
        $request = self::request(acceptLanguage: str_repeat('en;q=0.5,', 100));

        self::assertSame('fr', $this->resolver->resolve($request, null));
        self::assertTrue($request->attributes->get('_vary_by_language'));
    }

    public function test_an_answer_read_from_accept_language_varies_by_language(): void
    {
        $request = self::request(acceptLanguage: 'en');

        $this->resolver->resolve($request, null);

        self::assertTrue($request->attributes->get('_vary_by_language'));
    }

    public function test_the_default_reached_through_accept_language_varies_by_language_too(): void
    {
        $request = self::request();

        $this->resolver->resolve($request, null);

        self::assertTrue($request->attributes->get('_vary_by_language'));
    }

    /**
     * @return iterable<string, array{Request, ?User}>
     */
    public static function requestsAnsweredBeforeAcceptLanguage(): iterable
    {
        $englishUser = new User();
        $englishUser->locale = 'en';

        yield 'by the front end locale' => [self::request(acceptLanguage: 'fr', frontEndLocale: 'en'), null];
        yield 'by the user locale' => [self::request(acceptLanguage: 'fr'), $englishUser];
        yield 'by the cookie' => [self::request(cookie: 'en', acceptLanguage: 'fr'), null];
    }

    #[DataProvider('requestsAnsweredBeforeAcceptLanguage')]
    public function test_an_answer_found_before_accept_language_does_not_vary_by_language(Request $request, ?User $user): void
    {
        self::assertSame('en', $this->resolver->resolve($request, $user));
        self::assertFalse($request->attributes->has('_vary_by_language'));
    }

    public function test_a_single_enabled_locale_does_not_vary_by_language(): void
    {
        $resolver = self::resolver(['fr']);
        $request = self::request(acceptLanguage: 'en');

        $resolver->resolve($request, null);

        self::assertFalse($request->attributes->has('_vary_by_language'));
    }

    /**
     * @param string[] $enabledLocales
     */
    private static function resolver(array $enabledLocales): RequestLocaleResolver
    {
        return new RequestLocaleResolver($enabledLocales, 'fr', new LocalizedPath($enabledLocales, 'fr'));
    }

    private static function request(
        ?string $cookie = null,
        ?string $acceptLanguage = null,
        ?string $frontEndLocale = null,
        string $path = '/api/users/self',
    ): Request {
        $request = Request::create($path, cookies: $cookie !== null ? [RequestLocaleResolver::COOKIE => $cookie] : []);
        // Request::create() sends `en-us,en;q=0.5` unless told otherwise.
        $request->headers->remove('Accept-Language');
        if ($acceptLanguage !== null) {
            $request->headers->set('Accept-Language', $acceptLanguage);
        }
        if ($frontEndLocale !== null) {
            $request->headers->set(RequestLocaleResolver::HEADER, $frontEndLocale);
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
