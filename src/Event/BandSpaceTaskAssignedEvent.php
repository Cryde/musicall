<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\Task;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class BandSpaceTaskAssignedEvent extends Event
{
    /**
     * @param User[] $assignees the newly-added assignees
     */
    public function __construct(
        public readonly Task $task,
        public readonly User $actor,
        public readonly array $assignees,
    ) {
    }
}
