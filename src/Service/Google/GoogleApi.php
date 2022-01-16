<?php

namespace App\Service\Google;

class GoogleApi
{
    private \Google_Client $client;

    public function __construct(\Google_Client $client)
    {
        $this->client = $client;
    }

    public function getYoutube(): \Google_Service_YouTube
    {
        return new \Google_Service_YouTube($this->client);
    }
}
