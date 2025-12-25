<?php

declare(strict_types=1);

namespace App\ApiResource\Publication\Video;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Publication\VideoPreviewProvider;
use App\Validator\Publication\AlreadyExistingVideo;
use App\Validator\Publication\UrlVideo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Url;

#[Get(
    uriTemplate: '/publications/video/preview',
    openapi: new Operation(tags: ['Publications']),
    security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
    name: 'api_publication_video_preview',
    provider: VideoPreviewProvider::class,
    parameters: [
        'url' => new QueryParameter(
            key: 'url',
            description: 'The video you want preview from',
            constraints: [
                new Sequentially(constraints: [
                    new NotBlank(),
                    new Url(requireTld: true),
                    new UrlVideo(),
                    new AlreadyExistingVideo(),
                ])
            ]
        ),
    ]
)]
readonly class VideoPreview
{
    public function __construct(
        public string $url,
        public string $title,
        public string $description,
        public string $imageUrl,
    ) {
    }
}
