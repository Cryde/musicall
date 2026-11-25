<?php

declare(strict_types=1);

namespace App\Enum\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

/** What a videographer films (#1411), the kind's search filter. */
enum VideoSpeciality: string implements ServiceSpeciality
{
    case Clip = 'clip';
    case LiveSession = 'live_session';
    /** « Captation de concert ». */
    case ConcertFilm = 'concert_film';
    /** « Teaser et réseaux sociaux ». */
    case Teaser = 'teaser';
    /** « Montage seul »: footage the client sends. */
    case Editing = 'editing';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $speciality): string => $speciality->value, self::cases());
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::Clip => 'service_speciality.video.clip',
            self::LiveSession => 'service_speciality.video.live_session',
            self::ConcertFilm => 'service_speciality.video.concert_film',
            self::Teaser => 'service_speciality.video.teaser',
            self::Editing => 'service_speciality.video.editing',
        }, [], 'reference', $locale);
    }
}
