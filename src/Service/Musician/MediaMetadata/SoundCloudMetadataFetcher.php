<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaMetadata;

use App\Enum\Musician\MediaPlatform;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class SoundCloudMetadataFetcher implements MediaMetadataFetcherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function supports(MediaPlatform $platform): bool
    {
        return $platform === MediaPlatform::SOUNDCLOUD;
    }

    public function fetch(string $url, string $embedId): MediaMetadata
    {
        try {
            $response = $this->httpClient->request('GET', 'https://soundcloud.com/oembed', [
                'query' => [
                    'url' => $url,
                    'format' => 'json',
                ],
                'timeout' => 5,
            ]);

            $data = $response->toArray();

            $title = null;
            if (!empty($data['title'])) {
                $title = $data['title'];
                if (!empty($data['author_name']) && !str_contains($data['title'], $data['author_name'])) {
                    $title = $data['author_name'] . ' - ' . $data['title'];
                }
            }

            return new MediaMetadata(
                title: $title,
                thumbnailUrl: $data['thumbnail_url'] ?? null,
            );
        } catch (\Throwable) {
            return new MediaMetadata();
        }
    }
}
