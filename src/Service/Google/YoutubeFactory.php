<?php

namespace App\Service\Google;

use Google\Service\YouTube;

readonly class YoutubeFactory
{
    public function __construct(private GoogleApi $googleApi)
    {
    }

    public function __invoke(): YouTube
    {
        return $this->googleApi->getYoutube();
    }
}
