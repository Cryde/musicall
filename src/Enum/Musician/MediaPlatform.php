<?php

declare(strict_types=1);

namespace App\Enum\Musician;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum MediaPlatform: string implements TranslatableInterface
{
    case YOUTUBE = 'youtube';
    case SOUNDCLOUD = 'soundcloud';
    case SPOTIFY = 'spotify';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::YOUTUBE => 'media_platform.youtube',
            self::SOUNDCLOUD => 'media_platform.soundcloud',
            self::SPOTIFY => 'media_platform.spotify',
        }, [], 'reference', $locale);
    }
}
