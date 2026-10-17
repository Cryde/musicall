<?php

declare(strict_types=1);

namespace App\Locale;

use App\Entity\PublicationSubCategory;
use App\Repository\Forum\ForumRepository;
use App\Repository\Forum\ForumTopicRepository;
use App\Repository\PublicationRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Which locales a page has a version in, from its path. The server side of the router's rules
 * (assets/js/router/routes.js): a page with a translated interface, or content without a language (a video, a
 * gallery, a profile), is in every locale; a text publication is in its language and its translations'; a forum
 * and its topics are in their category's locale. Any other page (a member's own, the sign-in flow) has none.
 * tests/Fixtures/Locale/localized_paths.json holds the pages both sides must agree on.
 */
readonly class PageVersionsResolver
{
    // Matched on the page's path in the default locale.
    private const array IN_EVERY_LOCALE = [
        '#^/(decouvrir|band-space|contact|privacy|terms|mentions-legales|supprimer-mon-compte)?$#',
        '#^/(publications|cours|forums)$#',
        '#^/(publications|cours)/category/[^/]+$#',
        '#^/publications/tag/[^/]+$#',
        '#^/photos/[^/]+$#',
        '#^/rechercher-un-(musicien|professeur|guitariste|batteur|bassiste|chanteur|pianiste)$#',
        '#^/u/[^/]+(/musician|/teacher)?$#',
    ];
    private const string PUBLICATION = '#^/(publications|cours)/([^/]+)$#';
    private const string FORUM_TOPIC = '#^/forums/topic/([^/]+)(/\d+)?$#';
    private const string FORUM = '#^/forums/([^/]+)$#';
    // Under /forums, but a member's own list of topics.
    private const string MEMBER_TOPICS_SLUG = 'my-topics';

    /**
     * @param string[] $enabledLocales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')]
        private array $enabledLocales,
        #[Autowire(param: 'kernel.default_locale')]
        private string $defaultLocale,
        private LocalizedPath $localizedPath,
        private PublicationRepository $publicationRepository,
        private ForumRepository $forumRepository,
        private ForumTopicRepository $forumTopicRepository,
    ) {
    }

    /**
     * Null while a single locale is enabled, so the pages stay as they were, and for a page with no version
     * per locale or whose content is not found.
     */
    public function resolve(Request $request): ?PageVersions
    {
        if (count($this->enabledLocales) < 2) {
            return null;
        }

        $path = $request->getPathInfo();
        $defaultPath = $this->localizedPath->inDefaultLocale($path);
        foreach (self::IN_EVERY_LOCALE as $pattern) {
            if (preg_match($pattern, $defaultPath) === 1) {
                return $this->inEveryLocale($defaultPath, $this->localizedPath->localeOf($path));
            }
        }

        if (preg_match(self::FORUM_TOPIC, $defaultPath, $matches) === 1) {
            return $this->inOneLocale($defaultPath, $this->forumTopicRepository->findLocaleBySlug(rawurldecode($matches[1])));
        }

        if (preg_match(self::FORUM, $defaultPath, $matches) === 1 && $matches[1] !== self::MEMBER_TOPICS_SLUG) {
            $versions = $this->inOneLocale($defaultPath, $this->forumRepository->findLocaleBySlug(rawurldecode($matches[1])));
            // The one listing that pages through its URL: page N is a page of its own. The others keep their page
            // in memory, so a `?page=` there names page 1.
            $page = $this->pageNumber($request);

            return $versions !== null && $page > 1 ? $versions->onPage($page) : $versions;
        }

        if (preg_match(self::PUBLICATION, $defaultPath, $matches) === 1) {
            return $this->forPublication(rawurldecode($matches[2]), $this->localizedPath->localeOf($path));
        }

        return null;
    }

    private function forPublication(string $slug, string $urlLocale): ?PageVersions
    {
        $versions = $this->publicationRepository->findOnlineVersionsBySlug($slug);
        if ($versions === []) {
            return null;
        }

        $requested = $versions[0];
        // A video has no language: it is shown in every one.
        if ($requested['language'] === null) {
            return $this->inEveryLocale($this->publicationPath($requested), $urlLocale);
        }

        $paths = [];
        foreach ($versions as $version) {
            if ($version['language'] !== null && $this->isEnabled($version['language'])) {
                $paths[$version['language']] = $this->localizedPath->inLocale($this->publicationPath($version), $version['language']);
            }
        }

        return isset($paths[$requested['language']])
            ? new PageVersions($requested['language'], $this->inLocaleOrder($paths), $this->defaultLocale)
            : null;
    }

    /**
     * @param array{slug: string, subCategoryType: int} $version
     */
    private function publicationPath(array $version): string
    {
        $section = $version['subCategoryType'] === PublicationSubCategory::TYPE_COURSE ? '/cours/' : '/publications/';

        return $section . rawurlencode($version['slug']);
    }

    private function inEveryLocale(string $defaultPath, string $urlLocale): PageVersions
    {
        $paths = [];
        foreach ($this->enabledLocales as $locale) {
            $paths[$locale] = $this->localizedPath->inLocale($defaultPath, $locale);
        }

        return new PageVersions($urlLocale, $this->inLocaleOrder($paths), $this->defaultLocale);
    }

    /** Null for content not found, or written in a locale that is not enabled. */
    private function inOneLocale(string $defaultPath, ?string $contentLocale): ?PageVersions
    {
        if ($contentLocale === null || !$this->isEnabled($contentLocale)) {
            return null;
        }

        return new PageVersions($contentLocale, [$contentLocale => $this->localizedPath->inLocale($defaultPath, $contentLocale)], $this->defaultLocale);
    }

    private function pageNumber(Request $request): int
    {
        // all(), since get() throws a 400 on `?page[]=2`.
        $page = $request->query->all()['page'] ?? null;

        return is_string($page) && ctype_digit($page) ? (int) $page : 1;
    }

    /**
     * @param array<string, string> $paths
     *
     * @return array<string, string>
     */
    private function inLocaleOrder(array $paths): array
    {
        $order = array_flip(array_values(array_unique([$this->defaultLocale, ...$this->enabledLocales])));
        uksort($paths, static fn (string $a, string $b): int => $order[$a] <=> $order[$b]);

        return $paths;
    }

    private function isEnabled(string $locale): bool
    {
        return in_array($locale, $this->enabledLocales, true);
    }
}
