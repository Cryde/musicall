<?php

namespace App\Service\Google;
use App\Service\Google\Exception\YoutubeVideoNotFoundException;

class Youtube
{
    /**
     * @var \Google_Service_YouTube
     */
    private $youtube;
    /**
     * @var YoutubeUrlHelper
     */
    private $youtubeUrlHelper;

    /**
     * Youtube constructor.
     *
     * @param GoogleApi        $googleApi
     * @param YoutubeUrlHelper $youtubeUrlHelper
     */
    public function __construct(GoogleApi $googleApi, YoutubeUrlHelper $youtubeUrlHelper)
    {
        $this->youtube = $googleApi->getYoutube();
        $this->youtubeUrlHelper = $youtubeUrlHelper;
    }

    /**
     * @param string $videoUrl
     *
     * @return array
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
