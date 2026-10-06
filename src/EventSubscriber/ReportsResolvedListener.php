<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Report\Report;
use App\Enum\Notification\NotificationType;
use App\Event\ReportsResolvedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Tells each reporter what was decided about what they reported (DSA art. 16(5), terms §7.1, #1125).
 * Never who decided: the payload carries no moderator.
 */
#[AsEventListener]
readonly class ReportsResolvedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReportsResolvedEvent $event): void
    {
        $first = $event->reports[0] ?? null;
        if (!$first instanceof Report) {
            return;
        }

        // Nobody to tell on a closed or suspended account, nor a moderator who reported it themselves.
        $reporters = [];
        foreach ($event->reports as $report) {
            if ($report->reporter->isPubliclyVisible() && $report->reporter->id !== $event->moderator->id) {
                $reporters[] = $report->reporter;
            }
        }

        try {
            // All the reports are on one target, so one payload serves every reporter.
            $this->notificationCreator->createForRecipients($reporters, NotificationType::ReportResolved, [
                'target_type' => $first->targetType->value,
                'target_label' => $first->targetLabel(),
                'outcome' => $event->outcome->value,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create the report decision notifications', [
                'target_type' => $first->targetType->value,
                'target_id' => $first->targetId,
                'exception' => $e,
            ]);
        }
    }
}
