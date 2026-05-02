<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Task;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\TaskActivityCollectionProvider;

#[ApiResource(
    shortName: 'TaskActivity',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/tasks/{taskId}/activities',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'taskId' => new Link(fromClass: self::class, identifiers: ['taskId']),
            ],
            openapi: new Operation(tags: ['Band Space Task']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_task_activities_get_collection',
            provider: TaskActivityCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/tasks/{taskId}/activities/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'taskId' => new Link(fromClass: self::class, identifiers: ['taskId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Task']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_task_activities_get_item',
            provider: TaskActivityCollectionProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class TaskActivityResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $taskId;

    public string $actorId;
    public string $actorUsername;
    public ?string $actorProfilePictureUrl = null;
    public string $type;

    /** @var array<string, mixed>|null */
    public ?array $payload = null;

    public string $creationDatetime;
}
