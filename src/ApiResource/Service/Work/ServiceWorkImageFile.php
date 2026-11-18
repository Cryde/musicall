<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Work;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Service\Work\ServiceWorkImageStreamProvider;

/** An image of a work (#1407), streamed to whoever may read the service's page: a draft's stay its owner's. */
#[Get(
    uriTemplate: '/services/{serviceId}/works/{workId}/images/{id}',
    uriVariables: [
        'serviceId' => new Link(fromClass: self::class, identifiers: ['serviceId']),
        'workId' => new Link(fromClass: self::class, identifiers: ['workId']),
        'id' => new Link(fromClass: self::class, identifiers: ['id']),
    ],
    requirements: ['serviceId' => '[0-9a-fA-F\-]{36}', 'workId' => '[0-9a-fA-F\-]{36}', 'id' => '[0-9a-fA-F\-]{36}'],
    openapi: new Operation(tags: ['Service']),
    output: false,
    name: 'api_service_work_images_get',
    provider: ServiceWorkImageStreamProvider::class,
)]
class ServiceWorkImageFile
{
    #[ApiProperty(identifier: true)]
    public string $serviceId;

    #[ApiProperty(identifier: true)]
    public string $workId;

    #[ApiProperty(identifier: true)]
    public string $id;
}
