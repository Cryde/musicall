<?php declare(strict_types=1);

namespace App\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Names the thread and recipient of a queued "message received" email, so the worker can release the
 * thread's throttle once the email has finally failed (#1156). A stamp rather than an email header,
 * because Brevo forwards custom headers to the recipient.
 */
final readonly class MessageReceivedEmailStamp implements StampInterface
{
    public function __construct(
        public string $threadId,
        public string $recipientId,
    ) {
    }
}
