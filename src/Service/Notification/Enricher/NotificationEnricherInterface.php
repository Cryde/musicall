<?php declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\ApiResource\Notification\UserNotification;
use App\Enum\Notification\NotificationType;

/**
 * Read-time, per-type enricher: receives every feed DTO of its type and augments
 * their payload in place (e.g. live status), doing a single bulk query - no N+1.
 * Implementations are tagged `app.notification_enricher` (see config/services.yaml).
 */
interface NotificationEnricherInterface
{
    public function getType(): NotificationType;

    /**
     * @param UserNotification[] $notifications all of getType()
     */
    public function enrich(array $notifications): void;
}
