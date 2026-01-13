<?php

declare(strict_types=1);

namespace App\Enum\Musician;

enum MediaPlatform: string
{
    case YOUTUBE = 'youtube';
    case SOUNDCLOUD = 'soundcloud';
    case SPOTIFY = 'spotify';

    public function getLabel(): string
    {
        return match ($this) {
            self::YOUTUBE => 'YouTube',
            self::SOUNDCLOUD => 'SoundCloud',
            self::SPOTIFY => 'Spotify',
        };
    }
}
