<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Work;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Service\View\ServiceWorkImageView;
use App\State\Processor\Service\Work\MyServiceWorkImageDeleteProcessor;
use App\State\Processor\Service\Work\MyServiceWorkImageUploadProcessor;

/** An image of a work of the member's own service (#1407), sent as multipart, one at a time. */
#[ApiResource(
    shortName: 'MyServiceWorkImage',
    operations: [
        new Post(
            uriTemplate: '/user/services/{serviceId}/works/{workId}/images',
            uriVariables: [
                'serviceId' => new Link(fromClass: self::class, identifiers: ['serviceId']),
                'workId' => new Link(fromClass: self::class, identifiers: ['workId']),
            ],
            inputFormats: ['multipart' => ['multipart/form-data']],
            read: false,
            input: MyServiceWorkImageInput::class,
            name: 'api_user_services_work_images_upload',
            processor: MyServiceWorkImageUploadProcessor::class,
        ),
        // Not served, only the address an upload answers with.
        new NotExposed(),
        new Delete(
            read: false,
            name: 'api_user_services_work_images_delete',
            processor: MyServiceWorkImageDeleteProcessor::class,
        ),
    ],
    uriTemplate: '/user/services/{serviceId}/works/{workId}/images/{id}',
    uriVariables: [
        'serviceId' => new Link(fromClass: self::class, identifiers: ['serviceId']),
        'workId' => new Link(fromClass: self::class, identifiers: ['workId']),
        'id' => new Link(fromClass: self::class, identifiers: ['id']),
    ],
    requirements: ['serviceId' => '[0-9a-fA-F\-]{36}', 'workId' => '[0-9a-fA-F\-]{36}', 'id' => '[0-9a-fA-F\-]{36}'],
    openapi: new Operation(tags: ['Service']),
    security: 'is_granted("ROLE_USER")',
)]
class MyServiceWorkImage extends ServiceWorkImageView
{
    #[ApiProperty(identifier: true, readable: false, writable: false)]
    public string $serviceId;

    #[ApiProperty(identifier: true, readable: false, writable: false)]
    public string $workId;

    #[ApiProperty(identifier: true, writable: false)]
    public string $id;
}
