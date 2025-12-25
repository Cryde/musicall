<?php

declare(strict_types=1);

namespace App\Model\Publication;

readonly class YoutubeVideoInfo
{
    public function __construct(
        public string $url,
        public string $title,
        public string $description,
        public string $imageUrl,
    ) {
    }
}
