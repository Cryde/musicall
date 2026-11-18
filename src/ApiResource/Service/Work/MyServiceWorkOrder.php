<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Work;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Service\ServiceWork;
use App\State\Processor\Service\Work\MyServiceWorkImageReorderProcessor;
use App\State\Processor\Service\Work\MyServiceWorkReorderProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** The new order of a service's works, or of a work's images (#1407): every id, each once. */
#[Post(
    uriTemplate: '/user/services/{serviceId}/works/reorder',
    uriVariables: ['serviceId' => new Link(fromClass: self::class, identifiers: ['serviceId'])],
    requirements: ['serviceId' => '[0-9a-fA-F\-]{36}'],
    openapi: new Operation(tags: ['Service']),
    status: 204,
    security: 'is_granted("ROLE_USER")',
    output: false,
    read: false,
    name: 'api_user_services_works_reorder',
    processor: MyServiceWorkReorderProcessor::class,
)]
#[Post(
    uriTemplate: '/user/services/{serviceId}/works/{workId}/images/reorder',
    uriVariables: [
        'serviceId' => new Link(fromClass: self::class, identifiers: ['serviceId']),
        'workId' => new Link(fromClass: self::class, identifiers: ['workId']),
    ],
    requirements: ['serviceId' => '[0-9a-fA-F\-]{36}', 'workId' => '[0-9a-fA-F\-]{36}'],
    openapi: new Operation(tags: ['Service']),
    status: 204,
    security: 'is_granted("ROLE_USER")',
    output: false,
    read: false,
    name: 'api_user_services_work_images_reorder',
    processor: MyServiceWorkImageReorderProcessor::class,
)]
class MyServiceWorkOrder
{
    #[ApiProperty(identifier: true, readable: false, writable: false)]
    public string $serviceId;

    #[ApiProperty(identifier: true, readable: false, writable: false)]
    public string $workId;

    /** @var array<string> */
    #[Assert\Count(max: ServiceWork::MAX_PER_SERVICE)]
    #[Assert\All([new Assert\Type('string')])]
    public array $ids = [];
}
