<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\Task;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * An archived task is a closed record: archiving demands a task be done, so anything that could
 * reopen it, its status first of all, would leave the archive holding something it would never
 * have accepted. The board never renders an archived card and the drawer opens read-only, so only
 * a hand-crafted call gets there, which is exactly why the refusal belongs on the server.
 *
 * Two methods, because taking a task back out of the archive is itself a write and has to stay
 * possible. assertWritable() refuses every write and is what the paths that cannot express
 * unarchiving use; assertWritableForPayload() is the merge-patch door, which lets the one payload
 * that unarchives through and refuses the rest.
 *
 * Unlike SongWriteGuard and SetlistWriteGuard, which answer 409, this answers 422: the message and
 * the status are the ones the task PATCH has been returning since the invariant shipped, and one
 * invariant on one entity should not hand out two different codes depending on the door used.
 */
readonly class TaskWriteGuard
{
    private const string ARCHIVED_MESSAGE = 'Une tâche archivée est en lecture seule, désarchivez-la pour la modifier';

    public function assertWritable(Task $task): void
    {
        if ($task->archiveDatetime instanceof DateTimeImmutable) {
            throw new UnprocessableEntityHttpException(self::ARCHIVED_MESSAGE);
        }
    }

    /**
     * @param array<string, mixed> $payload raw merge-patch payload, used to detect explicitly-sent fields
     */
    public function assertWritableForPayload(Task $task, array $payload): void
    {
        if (array_diff(array_keys($payload), ['archived']) === []) {
            return;
        }

        $this->assertWritable($task);
    }
}
