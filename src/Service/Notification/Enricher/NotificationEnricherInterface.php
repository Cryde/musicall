<?php declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\ApiResource\Notification\UserNotification;
use App\Entity\User;
use App\Enum\Notification\NotificationType;

/**
 * Read-time, per-type enricher: receives every feed DTO of its type and augments
 * their payload in place (e.g. live status), doing a single bulk query - no N+1.
 * Implementations are tagged `app.notification_enricher` (see config/services.yaml).
 *
 * The recipient comes along because enrichment is relative to who is reading: refreshing a payload
 * hands out data as it stands right now, and what somebody is still allowed to see can have changed
 * since the notification was written (#817).
 */
interface NotificationEnricherInterface
{
    public function getType(): NotificationType;

    /**
     * @param UserNotification[] $notifications all of getType()
     * @param User $recipient the user whose feed is being read; every notification belongs to them
     */
    public function enrich(array $notifications, User $recipient): void;
}
