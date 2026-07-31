<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceDeletionStateChangedEvent;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the active members when an admin schedules a band space for deletion or cancels it (#748).
 * A grace period is worthless if nobody is told, so this is what gives the other members their 30 days
 * to retrieve their files or ask an admin to restore the space.
 *
 * One listener for both outcomes, discriminated by the event flag - same shape as
 * BandSpaceInvitationRespondedListener. Best-effort per the epic #689 resilience contract: dispatched
 * after the commit, and the whole body (including the member-resolution query) is wrapped so it can
 * never roll back or 500 the action. The actor is excluded; createForRecipients dedupes by user id.
 *
 * No enricher: band_space_name is a point-in-time label, and once the purge has run there is nothing
 * left to deep-link to anyway.
 */
#[AsEventListener]
readonly class BandSpaceDeletionStateChangedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceDeletionStateChangedEvent $event): void
    {
        $bandSpace = $event->bandSpace;
        $actor = $event->actor;
        $actorId = (string) $actor->id;

        try {
            $memberships = $this->bandSpaceMembershipRepository->findByBandSpace($bandSpace);
            $recipients = array_filter(
                array_map(static fn (BandSpaceMembership $membership): User => $membership->user, $memberships),
                static fn (User $user): bool => (string) $user->id !== $actorId,
            );
            if ($recipients === []) {
                return;
            }

            $type = $event->scheduled
                ? NotificationType::BandSpaceDeletionScheduled
                : NotificationType::BandSpaceDeletionCancelled;

            $this->notificationCreator->createForRecipients($recipients, $type, [
                'band_space_id' => (string) $bandSpace->id,
                'band_space_name' => $bandSpace->name,
                'scheduled_for' => $bandSpace->deletionScheduledDatetime?->format(\DateTimeInterface::ATOM),
                'actor_id' => $actorId,
                'actor_username' => $actor->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create band space deletion notifications', [
                'band_space_id' => (string) $bandSpace->id,
                'scheduled' => $event->scheduled,
                'exception' => $e,
            ]);
        }
    }
}
