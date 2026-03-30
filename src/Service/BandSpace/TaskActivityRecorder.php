<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\Task;
use App\Entity\BandSpace\TaskActivity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

readonly class TaskActivityRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function record(Task $task, User $actor, string $type, ?array $payload = null): TaskActivity
    {
        $activity = new TaskActivity();
        $activity->task = $task;
        $activity->actor = $actor;
        $activity->type = $type;
        $activity->payload = $payload;

        $this->entityManager->persist($activity);

        return $activity;
    }
}
