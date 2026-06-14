<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceFinanceSplitAssignedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the member a finance split is assigned to (#729), the single-recipient mirror of task
 * assignment (#721). Best-effort per the epic #689 resilience contract: the event is dispatched after
 * the split is committed, and this listener swallows + logs any failure so it can never roll back or
 * 500 the split write. The actor is excluded - adding a split for yourself must not self-notify.
 *
 * No enricher: `amount` / `entry_label` are point-in-time (the finance page is reachable by
 * band_space_id and the amount is informational), per the enricher rule.
 */
#[AsEventListener]
readonly class BandSpaceFinanceSplitAssignedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceFinanceSplitAssignedEvent $event): void
    {
        $split = $event->split;
        $member = $split->member;
        if ($member === null) {
            return;
        }

        // A split may legitimately be assigned to a former member (historical accounting -
        // the splits endpoint flags them via is_former_member), but notifying someone who
        // already left the band space is pointless, so skip non-active memberships.
        if ($member->status !== MembershipStatus::Active) {
            return;
        }

        $recipient = $member->user;
        if ((string) $recipient->id === (string) $event->actor->id) {
            return;
        }

        try {
            $this->notificationCreator->create($recipient, NotificationType::BandSpaceFinanceSplitAssigned, [
                'band_space_id' => (string) $member->bandSpace->id,
                'band_space_name' => $member->bandSpace->name,
                'entry_id' => (string) $split->entry->id,
                'entry_label' => $split->entry->label,
                'amount' => $split->amount,
                'actor_id' => (string) $event->actor->id,
                'actor_username' => $event->actor->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create band space finance split notification', [
                'split_id' => (string) $split->id,
                'exception' => $e,
            ]);
        }
    }
}
