<?php

declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\Enum\Notification\NotificationType;

/** See {@see AbstractProfilePostEnricher}. */
readonly class ProfilePostCommentNotificationEnricher extends AbstractProfilePostEnricher implements NotificationEnricherInterface
{
    public function getType(): NotificationType
    {
        return NotificationType::ProfilePostComment;
    }
}
