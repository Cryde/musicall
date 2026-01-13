<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaUrlParser;

use App\Enum\Musician\MediaPlatform;

readonly class SoundCloudUrlParser implements MediaUrlParserInterface
{
    public function supports(string $url): bool
    {
        return str_contains($url, 'soundcloud.com');
    }

    public function parse(string $url): ParsedMediaUrl
    {
        // Match soundcloud.com/artist/track or soundcloud.com/artist/sets/playlist
        if (preg_match('/soundcloud\.com\/([a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+)/', $url, $matches)) {
            // For SoundCloud, we store the path as embedId (will be used with oEmbed)
            return new ParsedMediaUrl(MediaPlatform::SOUNDCLOUD, $matches[1]);
        }

        throw new \InvalidArgumentException('Invalid SoundCloud URL');
    }
}
