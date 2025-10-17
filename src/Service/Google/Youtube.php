<?php declare(strict_types=1);

namespace App\Service\Google;
use App\Service\Google\Exception\YoutubeVideoNotFoundException;

class Youtube
{
    private readonly \Google_Service_YouTube $youtube;

    public function __construct(GoogleApi $googleApi, private readonly YoutubeUrlHelper $youtubeUrlHelper)
    {
        $this->youtube = $googleApi->getYoutube();
    }

    /**
     * @throws YoutubeVideoNotFoundException
     */
    public function getVideoInfo(string $videoUrl): array
    {
        $videoId = $this->youtubeUrlHelper->getVideoId($videoUrl);
        $listResponse = $this->youtube->videos->listVideos("snippet", ['id' => $videoId]);

        if (!$listResponse->items) {
            throw new YoutubeVideoNotFoundException();
        }

        $videoData = $listResponse->items[0];
        $title = $videoData->snippet->title;
        $description = $videoData->snippet->description;
        $thumbnails = $videoData->snippet->thumbnails;
        $imageUrl = null;

        if (isset($thumbnails['maxres'])) {
            $imageUrl = $thumbnails['maxres']->url;
        }
        if (null === $imageUrl) {
            $imageUrl = $thumbnails['high']->url;
        }

        return [
            'url'         => 'https://www.youtube.com/watch?v=' . $videoUrl,
            'title'       => $title,
            'description' => $description,
            'image_url'   => $imageUrl,
        ];
    }
}
