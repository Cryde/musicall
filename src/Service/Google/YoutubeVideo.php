<?php

declare(strict_types=1);

namespace App\Service\Google;

use App\Contracts\Google\Youtube\YoutubeRepositoryInterface;
use App\Model\Publication\YoutubeVideoInfo;
use App\Service\Google\Exception\YoutubeVideoNotFoundException;

readonly class YoutubeVideo
{
    private const string YOUTUBE_WATCH_URL = 'https://www.youtube.com/watch?v=';

    public function __construct(
        private YoutubeRepositoryInterface $youtubeRepository,
        private YoutubeUrlHelper $youtubeUrlHelper,
    ) {
    }

    /**
     * @throws YoutubeVideoNotFoundException
     */
    public function getVideoInfo(string $videoUrl): YoutubeVideoInfo
    {
        if (!$videoId = $this->youtubeUrlHelper->getVideoId($videoUrl)) {
            throw new YoutubeVideoNotFoundException('Invalid YouTube URL: ' . $videoUrl);
        }

        if (!$videoData = $this->youtubeRepository->fetchVideoData($videoId)) {
            throw new YoutubeVideoNotFoundException('Youtube video not found: ' . $videoUrl);
        }

        return new YoutubeVideoInfo(
            url: self::YOUTUBE_WATCH_URL . $videoId,
            title: $videoData['title'],
            description: $videoData['description'],
            imageUrl: $videoData['thumbnails']['maxres'] ?? $videoData['thumbnails']['high'],
        );
    }
}
