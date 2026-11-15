<?php declare(strict_types=1);

namespace App\Http;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Gives a file transfer more than PHP's default 30 seconds, which FrankenPHP counts in wall clock
 * time, so a 500 MB file on a slow link no longer dies halfway (#1390).
 */
class ExecutionTimeLimit implements ResetInterface
{
    /** 500 MB at about 1 MB/s, and still bounded so a stalled client cannot hold a worker thread forever. */
    public const int TRANSFER_SECONDS = 600;

    private ?int $limitBeforeTransfer = null;

    /**
     * Restarts the clock from now. An unlimited process (the CLI, the messenger worker) is left alone.
     */
    public function extendForTransfer(): void
    {
        $currentLimit = (int) ini_get('max_execution_time');
        if ($currentLimit === 0) {
            return;
        }

        $this->limitBeforeTransfer ??= $currentLimit;
        set_time_limit(self::TRANSFER_SECONDS);
    }

    /**
     * A FrankenPHP worker keeps the changed limit for the next request on the thread, so the
     * services resetter puts the original back before that request runs.
     */
    public function reset(): void
    {
        if ($this->limitBeforeTransfer === null) {
            return;
        }

        set_time_limit($this->limitBeforeTransfer);
        $this->limitBeforeTransfer = null;
    }
}
