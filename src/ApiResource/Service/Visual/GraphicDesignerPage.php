<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Visual;

use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\Service\ServiceKind;
use App\State\Provider\Service\VisualServicePageProvider;

/** A graphic designer's page (#1412), `/u/{username}/graphic-designer`. */
#[Get(
    uriTemplate: '/user/profile/{username}/graphic-designer',
    requirements: ['username' => '[^/]+'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    name: 'api_graphic_designer_page_get',
    provider: VisualServicePageProvider::class,
)]
class GraphicDesignerPage extends VisualServicePage
{
    public static function kind(): ServiceKind
    {
        return ServiceKind::GraphicDesign;
    }
}
