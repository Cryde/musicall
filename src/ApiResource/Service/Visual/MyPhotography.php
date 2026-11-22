<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Visual;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\Service\ServiceKind;
use App\State\Processor\Service\MyVisualServiceProcessor;
use App\State\Provider\Service\MyVisualServiceProvider;

#[ApiResource(
    shortName: 'MyPhotography',
    operations: [
        new Get(name: 'api_user_services_photography_get'),
        new Patch(name: 'api_user_services_photography_patch', processor: MyVisualServiceProcessor::class),
    ],
    uriTemplate: '/user/services/{id}/photography',
    requirements: ['id' => '[0-9a-fA-F\-]{36}'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    security: 'is_granted("ROLE_USER")',
    provider: MyVisualServiceProvider::class,
)]
class MyPhotography extends MyVisualService
{
    public static function kind(): ServiceKind
    {
        return ServiceKind::Photography;
    }
}
