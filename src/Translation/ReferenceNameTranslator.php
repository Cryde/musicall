<?php

declare(strict_types=1);

namespace App\Translation;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\PublicationSubCategory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The name of an admin-managed reference record in the request locale. The database holds the French
 * name, so French has no catalogue entry and reads it as is. Another language reads the `reference`
 * catalogue by slug and falls back to the database name, so a record an admin just added never shows a key.
 */
readonly class ReferenceNameTranslator
{
    private const string DOMAIN = 'reference';

    public function __construct(
        #[Autowire(service: 'translator')]
        private TranslatorInterface&TranslatorBagInterface $translator,
    ) {
    }

    public function instrument(Instrument $instrument): string
    {
        return $this->translate('instrument.' . $instrument->slug, $instrument->name);
    }

    /** The player of the instrument (« Batteur / Batteuse »), which profiles and announces show. */
    public function instrumentMusicianName(Instrument $instrument): string
    {
        return $this->translate('instrument_musician.' . $instrument->slug, $instrument->musicianName);
    }

    public function style(Style $style): string
    {
        return $this->translate('style.' . $style->slug, $style->name);
    }

    public function subCategory(PublicationSubCategory $subCategory): string
    {
        return $this->translate('publication_sub_category.' . $subCategory->slug, $subCategory->title);
    }

    private function translate(string $key, string $databaseName): string
    {
        if (!$this->translator->getCatalogue()->has($key, self::DOMAIN)) {
            return $databaseName;
        }

        return $this->translator->trans($key, [], self::DOMAIN);
    }
}
