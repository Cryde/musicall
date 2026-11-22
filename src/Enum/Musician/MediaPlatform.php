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
    /** Photography and video services only (#1411), see READ_BY_APP. */
    case VIMEO = 'vimeo';

    /**
     * What a link may embed by default: installed app builds read teacher, musician and wall media and know these
     * three only, so a platform added later is opted into where no app reads it.
     */
    public const array READ_BY_APP = [self::YOUTUBE, self::SOUNDCLOUD, self::SPOTIFY];

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::YOUTUBE => 'media_platform.youtube',
            self::SOUNDCLOUD => 'media_platform.soundcloud',
            self::SPOTIFY => 'media_platform.spotify',
            self::VIMEO => 'media_platform.vimeo',
        }, [], 'reference', $locale);
    }
}
