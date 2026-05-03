<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Task;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TaskBulkPatchProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tasks/bulk_patch',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TaskResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Task']),
    status: 204,
    security: "is_granted('ROLE_USER')",
    output: false,
    name: 'api_band_space_tasks_bulk_patch',
    processor: TaskBulkPatchProcessor::class,
)]
class TaskBulkPatch
{
    /** @var string[] */
    #[Assert\Count(min: 1, minMessage: 'Au moins une tâche doit être sélectionnée')]
    #[Assert\All([new Assert\NotBlank()])]
    public array $taskIds = [];

    public ?bool $archived = null;

    public ?string $categoryId = null;

    /** @var string[]|null */
    public ?array $assigneeIds = null;
}
