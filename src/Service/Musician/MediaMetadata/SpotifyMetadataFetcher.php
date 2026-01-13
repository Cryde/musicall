<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaMetadata;

use App\Enum\Musician\MediaPlatform;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class SpotifyMetadataFetcher implements MediaMetadataFetcherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function supports(MediaPlatform $platform): bool
    {
        return $platform === MediaPlatform::SPOTIFY;
    }

    public function fetch(string $url, string $embedId): MediaMetadata
    {
        try {
            $response = $this->httpClient->request('GET', 'https://open.spotify.com/oembed', [
                'query' => [
                    'url' => $url,
                ],
                'timeout' => 5,
            ]);

            $data = $response->toArray();

            return new MediaMetadata(
                title: $data['title'] ?? null,
                thumbnailUrl: $data['thumbnail_url'] ?? null,
            );
        } catch (\Throwable) {
            return new MediaMetadata();
        }
    }
}
