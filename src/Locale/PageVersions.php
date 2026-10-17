<?php

declare(strict_types=1);

namespace App\Locale;

/**
 * The versions of one page in each locale, for its canonical URL and its hreflang links.
 */
final readonly class PageVersions
{
    /**
     * @param string                $locale        the locale the page is shown in: its URL's, or its content's
     * @param array<string, string> $paths         the page's path in each locale it has a version in, the default first
     * @param string                $defaultLocale the locale whose version is x-default
     */
    public function __construct(
        public string $locale,
        public array $paths,
        private string $defaultLocale,
    ) {
    }

    public function canonicalPath(): string
    {
        return $this->paths[$this->locale];
    }

    /** The same versions, on page $page of a listing that pages through its URL. */
    public function onPage(int $page): self
    {
        return new self(
            $this->locale,
            array_map(static fn (string $path): string => $path . '?page=' . $page, $this->paths),
            $this->defaultLocale,
        );
    }

    /** The x-default version, none when the page has no version in the default locale. */
    public function defaultPath(): ?string
    {
        return $this->paths[$this->defaultLocale] ?? null;
    }
}
