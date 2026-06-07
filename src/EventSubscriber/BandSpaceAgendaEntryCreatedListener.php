<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceAgendaEntryCreatedEvent;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the other active members when a band space agenda entry is created (#722). Best-effort per
 * the epic #689 resilience contract: the event is dispatched after the entry is committed, and the
 * whole body - including the member-resolution query - is wrapped in try/catch so it can never roll
 * back or 500 the creation. The creator is excluded; createForRecipients dedupes by user id.
 *
 * No per-task-style note here, but see AgendaEntryNotificationEnricher: entry_title + event_datetime
 * are refreshed at feed-read because the agenda has no per-entry deep-link.
 */
#[AsEventListener]
readonly class BandSpaceAgendaEntryCreatedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceAgendaEntryCreatedEvent $event): void
    {
        $entry = $event->entry;
        $actor = $event->actor;
        $actorId = (string) $actor->id;

        try {
            $bandSpace = $entry->bandSpace;
            $memberships = $this->bandSpaceMembershipRepository->findByBandSpace($bandSpace);
            $recipients = array_filter(
                array_map(static fn (BandSpaceMembership $membership): User => $membership->user, $memberships),
                static fn (User $user): bool => (string) $user->id !== $actorId,
            );
            if ($recipients === []) {
                return;
            }

            $this->notificationCreator->createForRecipients($recipients, NotificationType::BandSpaceAgendaEntryCreated, [
                'band_space_id' => (string) $bandSpace->id,
                'band_space_name' => $bandSpace->name,
                'agenda_entry_id' => (string) $entry->id,
                'entry_title' => $entry->title,
                'event_datetime' => $entry->eventDatetime->format(\DateTimeInterface::ATOM),
                'actor_id' => $actorId,
                'actor_username' => $actor->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create agenda entry notifications', [
                'agenda_entry_id' => (string) $entry->id,
                'exception' => $e,
            ]);
        }
    }
}
