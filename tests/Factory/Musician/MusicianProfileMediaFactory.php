<?php

declare(strict_types=1);

namespace App\Tests\Factory\Musician;

use App\Entity\Musician\MusicianProfileMedia;
use App\Enum\Musician\MediaPlatform;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<MusicianProfileMedia>
 */
final class MusicianProfileMediaFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'platform' => MediaPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'embedId' => 'dQw4w9WgXcQ',
            'title' => self::faker()->sentence(),
            'position' => 0,
            'creationDatetime' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    public function asYouTube(): static
    {
        return $this->with([
            'platform' => MediaPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'embedId' => 'dQw4w9WgXcQ',
        ]);
    }

    public function asSoundCloud(): static
    {
        return $this->with([
            'platform' => MediaPlatform::SOUNDCLOUD,
            'url' => 'https://soundcloud.com/artist/track',
            'embedId' => 'artist/track',
        ]);
    }

    public function asSpotify(): static
    {
        return $this->with([
            'platform' => MediaPlatform::SPOTIFY,
            'url' => 'https://open.spotify.com/track/4i2uyexSsyLbmP8Gu2Knl9',
            'embedId' => 'track/4i2uyexSsyLbmP8Gu2Knl9',
        ]);
    }

    public static function class(): string
    {
        return MusicianProfileMedia::class;
    }
}
