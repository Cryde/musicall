<?php

declare(strict_types=1);

namespace App\Enum\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

/** What a photographer shoots (#1411), the kind's search filter. */
enum PhotographySpeciality: string implements ServiceSpeciality
{
    case Concert = 'concert';
    case Promo = 'promo';
    /** « Pochette »: a record's cover. */
    case Cover = 'cover';
    case Studio = 'studio';
    case BackstageTour = 'backstage_tour';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $speciality): string => $speciality->value, self::cases());
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::Concert => 'service_speciality.photography.concert',
            self::Promo => 'service_speciality.photography.promo',
            self::Cover => 'service_speciality.photography.cover',
            self::Studio => 'service_speciality.photography.studio',
            self::BackstageTour => 'service_speciality.photography.backstage_tour',
        }, [], 'reference', $locale);
    }
}
