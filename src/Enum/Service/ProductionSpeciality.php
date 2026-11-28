<?php

declare(strict_types=1);

namespace App\Enum\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

/** What a producer does (#1413), the kind's search filter. Mixing stays a sound engineer's: two services, never both. */
enum ProductionSpeciality: string implements ServiceSpeciality
{
    case Beatmaking = 'beatmaking';
    case Arrangement = 'arrangement';
    /** « Réalisation artistique »: producing a record, from the songs to the final mix. */
    case RecordProduction = 'record_production';
    case Composition = 'composition';
    /** « Topline »: the melody and the words over an instrumental. */
    case Topline = 'topline';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $speciality): string => $speciality->value, self::cases());
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::Beatmaking => 'service_speciality.production.beatmaking',
            self::Arrangement => 'service_speciality.production.arrangement',
            self::RecordProduction => 'service_speciality.production.record_production',
            self::Composition => 'service_speciality.production.composition',
            self::Topline => 'service_speciality.production.topline',
        }, [], 'reference', $locale);
    }
}
