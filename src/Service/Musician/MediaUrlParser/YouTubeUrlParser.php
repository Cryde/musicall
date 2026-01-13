<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaUrlParser;

use App\Enum\Musician\MediaPlatform;

readonly class YouTubeUrlParser implements MediaUrlParserInterface
{
    public function supports(string $url): bool
    {
        return str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be');
    }

    public function parse(string $url): ParsedMediaUrl
    {
        // Match youtube.com/watch?v=VIDEO_ID
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return new ParsedMediaUrl(MediaPlatform::YOUTUBE, $matches[1]);
        }

        // Match youtu.be/VIDEO_ID
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return new ParsedMediaUrl(MediaPlatform::YOUTUBE, $matches[1]);
        }

        // Match youtube.com/embed/VIDEO_ID
        if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return new ParsedMediaUrl(MediaPlatform::YOUTUBE, $matches[1]);
        }

        // Match youtube.com/shorts/VIDEO_ID
        if (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return new ParsedMediaUrl(MediaPlatform::YOUTUBE, $matches[1]);
        }

        throw new \InvalidArgumentException('Invalid YouTube URL');
    }
}
