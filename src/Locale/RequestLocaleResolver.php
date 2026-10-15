<?php

declare(strict_types=1);

namespace App\Locale;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * The locale a request wants: the user's own choice, then the locale cookie, then Accept-Language,
 * then the default. A value that is not an enabled locale falls through to the next step.
 */
readonly class RequestLocaleResolver
{
    public const string COOKIE = 'locale';
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
    ) {
    }

    public function resolve(Request $request, ?User $user): string
    {
        if ($user instanceof User && $this->isEnabled($user->locale)) {
            return $user->locale;
        }

        // all() rather than get(): get() throws a 400 on an array cookie (`locale[]=x`).
        $cookieLocale = $request->cookies->all()[self::COOKIE] ?? null;
        if (is_string($cookieLocale) && $this->isEnabled($cookieLocale)) {
            return $cookieLocale;
        }

        if (strlen((string) $request->headers->get('Accept-Language')) > self::MAX_ACCEPT_LANGUAGE_LENGTH) {
            return $this->defaultLocale;
        }

        // The default goes first: getPreferredLanguage() answers with the first candidate when nothing matches.
        $candidates = array_values(array_unique([$this->defaultLocale, ...$this->enabledLocales]));

        return $request->getPreferredLanguage($candidates) ?? $this->defaultLocale;
    }

    private function isEnabled(string $locale): bool
    {
        return in_array($locale, $this->enabledLocales, true);
    }
}
