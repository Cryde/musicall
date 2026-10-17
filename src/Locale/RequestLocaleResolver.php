<?php

declare(strict_types=1);

namespace App\Locale;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * The locale a request wants: the web front end's active locale, then the page's URL prefix, then the user's own
 * choice, then the locale cookie, then Accept-Language, then the default. A value that is not an enabled locale
 * falls through to the next step.
 */
readonly class RequestLocaleResolver
{
    public const string COOKIE = 'locale';
    // The web front end sends its active locale on every API call, so an English page gets English data whatever
    // the account or the browser says. Not Accept-Language: the mobile app relies on that one coming after the user.
    public const string HEADER = 'X-Locale';
    // Read by Symfony's ResponseListener, which then adds `Vary: Accept-Language`.
    private const string VARY_BY_LANGUAGE = '_vary_by_language';
    // A real header is a few dozen bytes, and parsing a huge crafted one costs seconds of CPU.
    private const int MAX_ACCEPT_LANGUAGE_LENGTH = 256;

    /**
     * @param string[] $enabledLocales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')]
        private array $enabledLocales,
        #[Autowire(param: 'kernel.default_locale')]
        private string $defaultLocale,
        private LocalizedPath $localizedPath,
    ) {
    }

    public function resolve(Request $request, ?User $user): string
    {
        // Compared whole and never parsed, so unlike Accept-Language its length costs nothing.
        $headerLocale = $request->headers->get(self::HEADER);
        if ($headerLocale !== null && $this->isEnabled($headerLocale)) {
            return $headerLocale;
        }

        // A page under `/en` is English, whatever the account, the cookie or the browser say. API calls have no prefix.
        $pathLocale = $this->localizedPath->prefixLocale($request->getPathInfo());
        if ($pathLocale !== null) {
            return $pathLocale;
        }

        if ($user instanceof User && $this->isEnabled($user->locale)) {
            return $user->locale;
        }

        // all() rather than get(): get() throws a 400 on an array cookie (`locale[]=x`).
        $cookieLocale = $request->cookies->all()[self::COOKIE] ?? null;
        if (is_string($cookieLocale) && $this->isEnabled($cookieLocale)) {
            return $cookieLocale;
        }

        // The default goes first: getPreferredLanguage() answers with the first candidate when nothing matches.
        $candidates = array_values(array_unique([$this->defaultLocale, ...$this->enabledLocales]));
        // With a single candidate the header cannot change the answer, so production responses stay as they were.
        if (count($candidates) > 1) {
            $request->attributes->set(self::VARY_BY_LANGUAGE, true);
        }

        if (strlen((string) $request->headers->get('Accept-Language')) > self::MAX_ACCEPT_LANGUAGE_LENGTH) {
            return $this->defaultLocale;
        }

        return $request->getPreferredLanguage($candidates) ?? $this->defaultLocale;
    }

    private function isEnabled(string $locale): bool
    {
        return in_array($locale, $this->enabledLocales, true);
    }
}
