<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Task;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TaskReorderProcessor;
use App\Validator\BandSpace\TaskReorderPositions;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tasks/reorder',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TaskResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Task']),
    status: 204,
    security: "is_granted('ROLE_USER')",
    output: false,
    name: 'api_band_space_tasks_reorder',
    processor: TaskReorderProcessor::class,
)]
#[TaskReorderPositions]
class TaskReorder
{
    /** @var list<mixed> */
    public array $positions = [];
}
