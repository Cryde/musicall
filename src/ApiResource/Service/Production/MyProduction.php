<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Production;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Service\Edit\MyServiceForm;
use App\Enum\Service\ServiceKind;
use App\State\Processor\Service\MyProductionProcessor;
use App\State\Provider\Service\MyProductionProvider;

/** A member's own production service, as its form edits it (#1413): what every kind's form edits, nothing of its own. */
#[ApiResource(
    shortName: 'MyProduction',
    operations: [
        new Get(name: 'api_user_services_production_get'),
        new Patch(name: 'api_user_services_production_patch', processor: MyProductionProcessor::class),
    ],
    uriTemplate: '/user/services/{id}/production',
    requirements: ['id' => '[0-9a-fA-F\-]{36}'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    security: 'is_granted("ROLE_USER")',
    provider: MyProductionProvider::class,
)]
class MyProduction extends MyServiceForm
{
    public static function kind(): ServiceKind
    {
        return ServiceKind::Production;
    }
}
