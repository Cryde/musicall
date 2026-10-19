<?php

declare(strict_types=1);

namespace App\Locale;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sends a visitor whose browser asks for any language but the default one to the page's English version, until they
 * choose a language. A request without Accept-Language, a search engine's, stays on the page it asked for.
 */
readonly class BrowserLocaleRedirect
{
    private const string FOREIGN_LOCALE = 'en';
    // A member's language is their account's, which the front end applies once it knows them.
    private const string MEMBER_COOKIE = 'refresh_token';

    public function __construct(
        #[Autowire(param: 'kernel.default_locale')]
        private string $defaultLocale,
    ) {
    }

    /** The URL to send the visitor to, or null to show them the page they asked for. */
    public function target(Request $request, PageVersions $versions): ?string
    {
        $target = $versions->paths[self::FOREIGN_LOCALE] ?? null;
        if ($target === null || $versions->locale !== $this->defaultLocale || $this->hasOwnLocale($request) || !$this->asksForAnotherLanguage($request)) {
            return null;
        }

        $query = $request->getQueryString();

        return $query === null ? $target : $target . '?' . $query;
    }

    private function hasOwnLocale(Request $request): bool
    {
        return $request->cookies->has(RequestLocaleResolver::COOKIE) || $request->cookies->has(self::MEMBER_COOKIE);
    }

    private function asksForAnotherLanguage(Request $request): bool
    {
        $header = (string) $request->headers->get('Accept-Language');
        if ($header === '' || strlen($header) > RequestLocaleResolver::MAX_ACCEPT_LANGUAGE_LENGTH) {
            return false;
        }

        // Sorted by quality: `en_US` for `en-US,fr;q=0.8`.
        $preferred = $request->getLanguages()[0] ?? '*';

        return $preferred !== '*' && strtolower(explode('_', $preferred)[0]) !== $this->defaultLocale;
    }
}
