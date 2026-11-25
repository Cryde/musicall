<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Visual;

use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\Service\ServiceKind;
use App\State\Provider\Service\VisualServicePageProvider;

/** A webmaster's page (#1412), `/u/{username}/webmaster`: their works are the sites they made. */
#[Get(
    uriTemplate: '/user/profile/{username}/webmaster',
    requirements: ['username' => '[^/]+'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    name: 'api_webmaster_page_get',
    provider: VisualServicePageProvider::class,
)]
class WebmasterPage extends VisualServicePage
{
    public static function kind(): ServiceKind
    {
        return ServiceKind::Web;
    }
}
