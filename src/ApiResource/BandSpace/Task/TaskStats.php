<?php

declare(strict_types=1);

namespace App\ApiResource\BandSpace\Task;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\Task\TaskStatsProvider;

// URI is /task-stats (kebab, not /tasks/stats) to avoid colliding with
// TaskResource's GET /tasks/{id}; API Platform's `priority:` only orders
// operations within a single resource, not across resources, so a
// distinct path segment is the simplest solution. Matches the
// /task-categories pattern.
#[Get(
    uriTemplate: '/band_spaces/{bandSpaceId}/task-stats',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Tasks']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    name: 'api_band_space_task_stats',
    provider: TaskStatsProvider::class,
)]
class TaskStats
{
    public string $bandSpaceId;
    public int $todo = 0;
    public int $inProgress = 0;
    public int $done = 0;
    public int $overdue = 0;
}
