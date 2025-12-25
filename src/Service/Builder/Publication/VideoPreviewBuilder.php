<?php

declare(strict_types=1);

namespace App\Service\Builder\Publication;

use App\ApiResource\Publication\Video\VideoPreview;
use App\Model\Publication\YoutubeVideoInfo;

readonly class VideoPreviewBuilder
{
    public function buildFromYoutubeVideoInfo(YoutubeVideoInfo $videoInfo): VideoPreview
    {
        return new VideoPreview(
            url: $videoInfo->url,
            title: $videoInfo->title,
            description: $videoInfo->description,
            imageUrl: $videoInfo->imageUrl,
        );
    }
}
