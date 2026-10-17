<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SocialPlatform: string implements TranslatableInterface
{
    case YOUTUBE = 'youtube';
    case SOUNDCLOUD = 'soundcloud';
    case INSTAGRAM = 'instagram';
    case FACEBOOK = 'facebook';
    case TWITTER = 'twitter';
    case TIKTOK = 'tiktok';
    case SPOTIFY = 'spotify';
    case BANDCAMP = 'bandcamp';
    case WEBSITE = 'website';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::YOUTUBE => 'social_platform.youtube',
            self::SOUNDCLOUD => 'social_platform.soundcloud',
            self::INSTAGRAM => 'social_platform.instagram',
            self::FACEBOOK => 'social_platform.facebook',
            self::TWITTER => 'social_platform.twitter',
            self::TIKTOK => 'social_platform.tiktok',
            self::SPOTIFY => 'social_platform.spotify',
            self::BANDCAMP => 'social_platform.bandcamp',
            self::WEBSITE => 'social_platform.website',
        }, [], 'reference', $locale);
    }

}
