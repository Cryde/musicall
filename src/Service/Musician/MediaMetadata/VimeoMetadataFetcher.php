<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaMetadata;

use App\Enum\Musician\MediaPlatform;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** A Vimeo video's title and thumbnail, through its oEmbed endpoint (#1411). */
readonly class VimeoMetadataFetcher implements MediaMetadataFetcherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function supports(MediaPlatform $platform): bool
    {
        return $platform === MediaPlatform::VIMEO;
    }

    public function fetch(string $url, string $embedId): MediaMetadata
    {
        try {
            $data = $this->httpClient->request('GET', 'https://vimeo.com/api/oembed.json', [
                'query' => ['url' => $url],
                'timeout' => 5,
            ])->toArray();

            $title = $data['title'] ?? null;
            $thumbnailUrl = $data['thumbnail_url'] ?? null;

            return new MediaMetadata(
                title: is_string($title) && $title !== '' ? $title : null,
                thumbnailUrl: is_string($thumbnailUrl) ? $thumbnailUrl : null,
            );
        } catch (\Throwable) {
            return new MediaMetadata();
        }
    }
}
