<?php

declare(strict_types=1);

namespace App\Enum\Musician;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum AvailabilityStatus: string implements TranslatableInterface
{
    case LOOKING_FOR_BAND = 'looking_for_band';
    case AVAILABLE_FOR_SESSIONS = 'available_for_sessions';
    case OPEN_TO_COLLABORATIONS = 'open_to_collaborations';
    case NOT_AVAILABLE = 'not_available';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::LOOKING_FOR_BAND => 'availability_status.looking_for_band',
            self::AVAILABLE_FOR_SESSIONS => 'availability_status.available_for_sessions',
            self::OPEN_TO_COLLABORATIONS => 'availability_status.open_to_collaborations',
            self::NOT_AVAILABLE => 'availability_status.not_available',
        }, [], 'reference', $locale);
    }
}
