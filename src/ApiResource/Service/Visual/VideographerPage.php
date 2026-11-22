<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Visual;

use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\Service\ServiceKind;
use App\State\Provider\Service\VisualServicePageProvider;

/** A videographer's page (#1411), `/u/{username}/videographer`. */
#[Get(
    uriTemplate: '/user/profile/{username}/videographer',
    requirements: ['username' => '[^/]+'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    name: 'api_videographer_page_get',
    provider: VisualServicePageProvider::class,
)]
class VideographerPage extends VisualServicePage
{
    public static function kind(): ServiceKind
    {
        return ServiceKind::Video;
    }
}
