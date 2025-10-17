<?php declare(strict_types=1);

namespace App\Service\Google;

use Google\Client;
use Google\Service\YouTube as GoogleYouTube;

class GoogleApi
{
    public function __construct(private readonly Client $client)
    {
    }

    public function getYoutube(): GoogleYouTube
    {
        return new GoogleYouTube($this->client);
    }
}
