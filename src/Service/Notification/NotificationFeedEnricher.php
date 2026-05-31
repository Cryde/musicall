<?php declare(strict_types=1);

namespace App\Service\Notification;

use App\ApiResource\Notification\UserNotification;
use App\Service\Notification\Enricher\NotificationEnricherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Runs the per-type {@see NotificationEnricherInterface} strategies over a built
 * feed: groups DTOs by type and hands each group to its enricher, so each enricher
 * does one batch query per page (never per notification).
 */
readonly class NotificationFeedEnricher
{
    /** @var array<string, NotificationEnricherInterface> type value => enricher */
    private array $enrichersByType;

    /**
     * @param iterable<NotificationEnricherInterface> $enrichers
     */
    public function __construct(
        #[AutowireIterator('app.notification_enricher')]
        iterable $enrichers,
        private LoggerInterface $logger,
    ) {
        $map = [];
        foreach ($enrichers as $enricher) {
            $map[$enricher->getType()->value] = $enricher;
        }
        $this->enrichersByType = $map;
    }

    /**
     * @param UserNotification[] $notifications
     */
    public function enrich(array $notifications): void
    {
        $groups = [];
        foreach ($notifications as $notification) {
            $groups[$notification->type][] = $notification;
        }

        foreach ($groups as $type => $group) {
            $enricher = $this->enrichersByType[$type] ?? null;
            if ($enricher === null) {
                continue;
            }

            // Read-time enrichment is best-effort: a failing enricher must degrade to the
            // stored point-in-time payload, never 500 the whole feed (epic #689 contract).
            // Isolated per type so one broken enricher can't take down the others.
            try {
                $enricher->enrich($group);
            } catch (\Throwable $e) {
                $this->logger->error('Notification feed enrichment failed; serving stored payload', [
                    'type' => $type,
                    'exception' => $e,
                ]);
            }
        }
    }
}
