<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Task;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TaskMoveProcessor;
use App\Validator\BandSpace\TaskMovePayload;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tasks/move',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TaskResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Task']),
    status: 200,
    security: "is_granted('ROLE_USER')",
    output: TaskResource::class,
    name: 'api_band_space_tasks_move',
    processor: TaskMoveProcessor::class,
)]
#[TaskMovePayload]
class TaskMove
{
    #[Assert\NotBlank(message: 'Veuillez spécifier un identifiant de tâche')]
    public string $taskId;

    #[Assert\Choice(choices: ['todo', 'in_progress', 'done'], message: 'Le statut doit être "todo", "in_progress" ou "done"')]
    public string $status;

    /** @var list<mixed> */
    public array $positions = [];
}
