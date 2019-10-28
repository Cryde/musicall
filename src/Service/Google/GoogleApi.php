<?php

namespace App\Service\Google;

class GoogleApi
{
    /**
     * @var \Google_Client
     */
    private $client;

    public function __construct(\Google_Client $client)
    {
        $this->client = $client;
    }

    public function getYoutube()
    {
        return new \Google_Service_YouTube($this->client);
    }
}
