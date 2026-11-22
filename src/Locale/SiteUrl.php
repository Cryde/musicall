<?php

declare(strict_types=1);

namespace App\Locale;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/** The absolute address of a page of the site, in the current locale. */
readonly class SiteUrl
{
    public function __construct(
        private LocalizedPath $localizedPath,
        private TranslatorInterface $translator,
        #[Autowire('%env(DEFAULT_URI)%')]
        private string $siteOrigin,
    ) {
    }

    public function of(string $defaultLocalePath): string
    {
        return $this->siteOrigin . $this->localizedPath->inLocale($defaultLocalePath, $this->translator->getLocale());
    }
}
