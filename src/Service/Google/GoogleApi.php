<?php

namespace App\Service\Google;

class GoogleApi
{
    public function __construct(private readonly \Google_Client $client)
    {
    }

    public function getYoutube(): \Google_Service_YouTube
    {
        return new \Google_Service_YouTube($this->client);
    }
}
