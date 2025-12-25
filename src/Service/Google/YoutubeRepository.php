<?php

namespace App\Service\Google;

use App\Contracts\Google\Youtube\YoutubeRepositoryInterface;
use Google\Service\Exception;
use Google\Service\YouTube;

readonly class YoutubeRepository implements YoutubeRepositoryInterface
{
    public function __construct(
        private YouTube $youtube,
    ) {
    }

    /**
     * @return array{title: string, description: string, thumbnails: array<string, string>}|null
     *
     * @throws Exception
     */
    public function fetchVideoData(string $videoId): ?array
    {
        $listResponse = $this->youtube->videos->listVideos('snippet', ['id' => $videoId]);
        if (!$listResponse->getItems()) {
            return null;
        }
        $snippet = $listResponse->getItems()[0]->getSnippet();
        $thumbnails = $snippet->getThumbnails();

        return [
            'title'       => $snippet->getTitle(),
            'description' => $snippet->getDescription(),
            'thumbnails'  => [
                'maxres' => $thumbnails->getMaxres()?->getUrl(),
                'high'   => $thumbnails->getHigh()?->getUrl(),
            ],
        ];
    }
}
