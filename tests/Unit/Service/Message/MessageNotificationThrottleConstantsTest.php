<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Message;

use App\EventSubscriber\UserActivityListener;
use App\Service\Procedure\Message\MessageSenderProcedure;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Guard the contract between two related but file-distant constants:
 *
 * - UserActivityListener::WRITE_INTERVAL_SECONDS — how often we refresh
 *   User.lastActivityDatetime at most (write throttle).
 * - MessageSenderProcedure::ACTIVE_WINDOW_SECONDS — how far back a recipient
 *   counts as "presently active" for the message-email skip (#712).
 *
 * The write throttle MUST be strictly tighter than the active window; otherwise
 * an active user can be misclassified as idle simply because their
 * lastActivityDatetime is up to a throttle-interval stale, and we would email
 * them when we shouldn't.
 */
class MessageNotificationThrottleConstantsTest extends TestCase
{
    public function test_write_throttle_is_strictly_tighter_than_active_window(): void
    {
        $writeInterval = (new ReflectionClass(UserActivityListener::class))
            ->getConstant('WRITE_INTERVAL_SECONDS');
        $activeWindow = (new ReflectionClass(MessageSenderProcedure::class))
            ->getConstant('ACTIVE_WINDOW_SECONDS');

        $this->assertIsInt($writeInterval);
        $this->assertIsInt($activeWindow);
        $this->assertLessThan(
            $activeWindow,
            $writeInterval,
            sprintf(
                'UserActivityListener::WRITE_INTERVAL_SECONDS (%d) must be < ' .
                'MessageSenderProcedure::ACTIVE_WINDOW_SECONDS (%d). ' .
                'If the throttle catches up with the window, an actively-using ' .
                'recipient can appear idle for up to one throttle interval and ' .
                'wrongly receive a message email.',
                $writeInterval,
                $activeWindow,
            ),
        );
    }
}
