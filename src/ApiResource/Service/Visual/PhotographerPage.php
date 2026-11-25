<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Visual;

use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\Service\ServiceKind;
use App\State\Provider\Service\VisualServicePageProvider;

/** A photographer's page (#1411), `/u/{username}/photographer`. */
#[Get(
    uriTemplate: '/user/profile/{username}/photographer',
    requirements: ['username' => '[^/]+'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    name: 'api_photographer_page_get',
    provider: VisualServicePageProvider::class,
)]
class PhotographerPage extends VisualServicePage
{
    public static function kind(): ServiceKind
    {
        return ServiceKind::Photography;
    }
}
