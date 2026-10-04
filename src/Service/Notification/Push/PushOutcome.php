<?php declare(strict_types=1);

namespace App\Service\Notification\Push;

enum PushOutcome
{
    /** Delivered, nothing to deliver to, or failed in a way a second attempt would not fix. */
    case Finished;

    /** FCM was unreachable for every device, so retrying cannot deliver anything twice. */
    case RetryLater;
}
