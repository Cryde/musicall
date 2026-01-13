<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaUrlParser;

use App\Enum\Musician\MediaPlatform;

readonly class SpotifyUrlParser implements MediaUrlParserInterface
{
    public function supports(string $url): bool
    {
        return str_contains($url, 'spotify.com');
    }

    public function parse(string $url): ParsedMediaUrl
    {
        // Match open.spotify.com/track/TRACK_ID or open.spotify.com/intl-xx/track/TRACK_ID
        if (preg_match('/open\.spotify\.com\/(?:intl-[a-z]{2}\/)?track\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return new ParsedMediaUrl(MediaPlatform::SPOTIFY, 'track/' . $matches[1]);
        }

        // Match open.spotify.com/album/ALBUM_ID or open.spotify.com/intl-xx/album/ALBUM_ID
        if (preg_match('/open\.spotify\.com\/(?:intl-[a-z]{2}\/)?album\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return new ParsedMediaUrl(MediaPlatform::SPOTIFY, 'album/' . $matches[1]);
        }

        // Match open.spotify.com/artist/ARTIST_ID or open.spotify.com/intl-xx/artist/ARTIST_ID
        if (preg_match('/open\.spotify\.com\/(?:intl-[a-z]{2}\/)?artist\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return new ParsedMediaUrl(MediaPlatform::SPOTIFY, 'artist/' . $matches[1]);
        }

        // Match open.spotify.com/playlist/PLAYLIST_ID or open.spotify.com/intl-xx/playlist/PLAYLIST_ID
        if (preg_match('/open\.spotify\.com\/(?:intl-[a-z]{2}\/)?playlist\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return new ParsedMediaUrl(MediaPlatform::SPOTIFY, 'playlist/' . $matches[1]);
        }

        throw new \InvalidArgumentException('Invalid Spotify URL');
    }
}
