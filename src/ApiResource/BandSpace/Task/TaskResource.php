<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Task;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TaskDeleteProcessor;
use App\State\Processor\BandSpace\TaskUpdateProcessor;
use App\State\Provider\BandSpace\TaskCollectionProvider;
use App\State\Provider\BandSpace\TaskItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Task',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/tasks',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Task']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_tasks_get_collection',
            provider: TaskCollectionProvider::class,
            parameters: [
                'status' => new QueryParameter(key: 'status'),
                'category_id' => new QueryParameter(key: 'category_id'),
                'assignee_id' => new QueryParameter(key: 'assignee_id'),
                'priority' => new QueryParameter(key: 'priority'),
                'archived' => new QueryParameter(key: 'archived'),
            ],
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/tasks/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Task']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_tasks_get_item',
            provider: TaskItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/tasks/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Task']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_tasks_patch',
            provider: TaskItemProvider::class,
            processor: TaskUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/tasks/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Task']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_tasks_delete',
            provider: TaskItemProvider::class,
            processor: TaskDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class TaskResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    public string $title;

    public ?string $description = null;

    #[Assert\Choice(choices: ['todo', 'in_progress', 'done'], message: 'Le statut doit être "todo", "in_progress" ou "done"')]
    public string $status;

    #[Assert\Choice(choices: ['normal', 'high', 'urgent'], message: 'La priorité doit être "normal", "high" ou "urgent"')]
    public string $priority;

    public ?string $dueDate = null;
    public string $createdById;
    public string $createdByUsername;
    public ?string $categoryId = null;
    public ?string $categoryName = null;

    /** @var array<int, array{id: string, username: string}> */
    public array $assignees = [];

    public ?string $archiveDatetime = null;
    public int $position = 0;
    public string $creationDatetime;
    public ?string $updateDatetime = null;
}
