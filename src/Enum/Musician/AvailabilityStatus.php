<?php

declare(strict_types=1);

namespace App\Enum\Musician;

enum AvailabilityStatus: string
{
    case LOOKING_FOR_BAND = 'looking_for_band';
    case AVAILABLE_FOR_SESSIONS = 'available_for_sessions';
    case OPEN_TO_COLLABORATIONS = 'open_to_collaborations';
    case NOT_AVAILABLE = 'not_available';

    public function getLabel(): string
    {
        return match ($this) {
            self::LOOKING_FOR_BAND => 'Cherche un groupe',
            self::AVAILABLE_FOR_SESSIONS => 'Disponible pour sessions/concerts',
            self::OPEN_TO_COLLABORATIONS => 'Ouvert aux collaborations',
            self::NOT_AVAILABLE => 'Non disponible',
        };
    }
}
