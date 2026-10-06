<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\Notification\NotificationType;
use App\Event\ReportCreatedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** The receipt a reporter is owed (DSA art. 16(4), terms §7.1, #1125). */
#[AsEventListener]
readonly class ReportCreatedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReportCreatedEvent $event): void
    {
        $report = $event->report;

        try {
            $this->notificationCreator->create($report->reporter, NotificationType::ReportReceived, [
                'target_type' => $report->targetType->value,
                'target_label' => $report->targetLabel(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create the report receipt notification', [
                'report_id' => (string) $report->id,
                'exception' => $e,
            ]);
        }
    }
}
