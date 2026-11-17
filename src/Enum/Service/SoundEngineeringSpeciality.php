<?php

declare(strict_types=1);

namespace App\Enum\Service;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** What a sound engineer does (#1410), the kind's search filter. */
enum SoundEngineeringSpeciality: string implements TranslatableInterface
{
    case Mixing = 'mixing';
    case Mastering = 'mastering';
    case Recording = 'recording';
    case LiveRecording = 'live_recording';
    /** « Son en concert »: front of house and monitors. */
    case LiveSound = 'live_sound';
    case Editing = 'editing';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $speciality): string => $speciality->value, self::cases());
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::Mixing => 'service_speciality.mixing',
            self::Mastering => 'service_speciality.mastering',
            self::Recording => 'service_speciality.recording',
            self::LiveRecording => 'service_speciality.live_recording',
            self::LiveSound => 'service_speciality.live_sound',
            self::Editing => 'service_speciality.editing',
        }, [], 'reference', $locale);
    }
}
