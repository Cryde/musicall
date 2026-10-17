<?php

declare(strict_types=1);

namespace App\Locale;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Where a page lives in each locale. The default locale's pages have no prefix, every other enabled locale has
 * them under `/{locale}`, and a few landing pages translate their first segment too, so `/cours` is `/en/lessons`.
 * assets/js/i18n/localizedPath.js is the router's copy, and tests/Fixtures/Locale/localized_paths.json keeps the
 * two in step. A locale that is not enabled has neither a prefix nor translated segments, so `/lessons` is
 * nothing while English is off.
 */
readonly class LocalizedPath
{
    // French segment => its translation, per locale. Only the landing pages people search for by name.
    private const array TRANSLATED_SEGMENTS = [
        'en' => [
            'cours' => 'lessons',
            'decouvrir' => 'discover',
            'mentions-legales' => 'legal-notice',
            'supprimer-mon-compte' => 'delete-my-account',
            'rechercher-un-musicien' => 'find-a-musician',
            'rechercher-un-professeur' => 'find-a-teacher',
            'rechercher-un-guitariste' => 'find-a-guitarist',
            'rechercher-un-batteur' => 'find-a-drummer',
            'rechercher-un-bassiste' => 'find-a-bassist',
            'rechercher-un-chanteur' => 'find-a-singer',
            'rechercher-un-pianiste' => 'find-a-pianist',
        ],
    ];

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

    /** The locale a path's prefix names, or null when it has none. */
    public function prefixLocale(string $path): ?string
    {
        $firstSegment = explode('/', $path, 3)[1] ?? '';

        return $this->isPrefix($firstSegment) ? $firstSegment : null;
    }

    public function localeOf(string $path): string
    {
        return $this->prefixLocale($path) ?? $this->defaultLocale;
    }

    /** Untouched while the default locale is the only one, so production paths reach their readers as they came. */
    public function inDefaultLocale(string $path): string
    {
        return array_diff($this->enabledLocales, [$this->defaultLocale]) === [] ? $path : $this->inLocale($path, $this->defaultLocale);
    }

    /** The path of the same page in $locale, from its path in any locale. */
    public function inLocale(string $path, string $locale): string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
        if ($segments !== [] && $this->isPrefix($segments[0])) {
            array_shift($segments);
        }
        if ($segments !== []) {
            $segments[0] = $this->segmentInLocale($segments[0], $locale);
        }
        if ($locale !== $this->defaultLocale) {
            array_unshift($segments, $locale);
        }

        return '/' . implode('/', $segments);
    }

    private function segmentInLocale(string $segment, string $locale): string
    {
        $defaultSegment = $segment;
        foreach (self::TRANSLATED_SEGMENTS as $translationLocale => $translations) {
            $found = $this->isPrefix($translationLocale) ? array_search($segment, $translations, true) : false;
            if ($found !== false) {
                $defaultSegment = $found;
                break;
            }
        }

        return $this->isPrefix($locale) ? self::TRANSLATED_SEGMENTS[$locale][$defaultSegment] ?? $defaultSegment : $defaultSegment;
    }

    private function isPrefix(string $segment): bool
    {
        return $segment !== $this->defaultLocale && in_array($segment, $this->enabledLocales, true);
    }
}
