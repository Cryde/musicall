<?php

declare(strict_types=1);

namespace App\Enum;

enum SocialPlatform: string
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

    public function getLabel(): string
    {
        return match ($this) {
            self::YOUTUBE => 'YouTube',
            self::SOUNDCLOUD => 'SoundCloud',
            self::INSTAGRAM => 'Instagram',
            self::FACEBOOK => 'Facebook',
            self::TWITTER => 'X (Twitter)',
            self::TIKTOK => 'TikTok',
            self::SPOTIFY => 'Spotify',
            self::BANDCAMP => 'Bandcamp',
            self::WEBSITE => 'Site web',
        };
    }

}
