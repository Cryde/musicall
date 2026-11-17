<?php

declare(strict_types=1);

namespace App\ApiResource\Service;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Service\MyServiceCreateProcessor;
use App\State\Processor\Service\MyServiceDeleteProcessor;
use App\Enum\Service\ServiceStatus;
use App\State\Processor\Service\MyServiceStatusProcessor;
use App\State\Provider\Service\MyServiceCollectionProvider;
use Symfony\Component\HttpFoundation\Response;

/** A member's own service, as « Mes services » lists it (#1406). */
#[ApiResource(
    shortName: 'MyService',
    operations: [
        new GetCollection(
            uriTemplate: '/user/services',
            paginationEnabled: false,
            name: 'api_user_services_list',
            provider: MyServiceCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/user/services',
            input: MyServiceInput::class,
            name: 'api_user_services_create',
            processor: MyServiceCreateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/user/services/{id}',
            uriVariables: ['id'],
            requirements: ['id' => self::ID_PATTERN],
            read: false,
            name: 'api_user_services_delete',
            processor: MyServiceDeleteProcessor::class,
        ),
        new Post(
            uriTemplate: '/user/services/{id}/publish',
            uriVariables: ['id'],
            requirements: ['id' => self::ID_PATTERN],
            status: Response::HTTP_OK,
            read: false,
            deserialize: false,
            validate: false,
            name: 'api_user_services_publish',
            processor: MyServiceStatusProcessor::class,
            extraProperties: [MyServiceStatusProcessor::TARGET_STATUS => ServiceStatus::Published],
        ),
        new Post(
            uriTemplate: '/user/services/{id}/unpublish',
            uriVariables: ['id'],
            requirements: ['id' => self::ID_PATTERN],
            status: Response::HTTP_OK,
            read: false,
            deserialize: false,
            validate: false,
            name: 'api_user_services_unpublish',
            processor: MyServiceStatusProcessor::class,
            extraProperties: [MyServiceStatusProcessor::TARGET_STATUS => ServiceStatus::Draft],
        ),
    ],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    security: 'is_granted("ROLE_USER")',
)]
class MyService
{
    private const string ID_PATTERN = '[0-9a-fA-F\-]{36}';

    #[ApiProperty(identifier: true)]
    public string $id;

    /** A ServiceKind value. */
    public string $kind;

    public ?string $headline = null;

    /** A ServiceStatus value. */
    public string $status;

    /** The share of its page filled in, from 0 to 100. */
    public int $completeness;

    /**
     * What it needs before it can be published, as ServicePart values: empty once it can be.
     *
     * @var list<string>
     */
    public array $missingToPublish = [];
}
