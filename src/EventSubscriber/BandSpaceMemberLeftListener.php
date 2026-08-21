<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceMemberLeftEvent;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the remaining admins when a member quits a band space (#833). Departing deactivates that
 * member's finance recurrences and drops their planned entries, so an admin who is not told has a
 * budget that moved under them and nothing but the activity log to explain it.
 *
 * Admins only, not the whole roster: the departure leaves book-keeping for whoever can act on it.
 * Best-effort per the epic #689 resilience contract: dispatched after the commit, and the whole body
 * (including the admin-resolution query) is wrapped so it can never roll back or 500 the departure.
 *
 * No enricher: band_space_name and the username are point-in-time labels, and a membership that no
 * longer exists has no page of its own to deep-link to.
 */
#[AsEventListener]
readonly class BandSpaceMemberLeftListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceMemberLeftEvent $event): void
    {
        $membership = $event->membership;
        $actorId = (string) $membership->user->id;

        try {
            $bandSpace = $membership->bandSpace;
            // The departure is committed by now, so the leaver's own membership is no longer active and
            // the query cannot return it. Filtered anyway, for symmetry with the sibling listeners and
            // so an admin who quits is never told about their own departure.
            $recipients = array_filter(
                array_map(
                    static fn (BandSpaceMembership $adminMembership): User => $adminMembership->user,
                    $this->bandSpaceMembershipRepository->findActiveAdmins($bandSpace),
                ),
                static fn (User $admin): bool => (string) $admin->id !== $actorId,
            );
            if ($recipients === []) {
                return;
            }

            $this->notificationCreator->createForRecipients($recipients, NotificationType::BandSpaceMemberLeft, [
                'band_space_id' => (string) $bandSpace->id,
                'band_space_name' => $bandSpace->name,
                'actor_id' => $actorId,
                'actor_username' => $membership->user->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create band space member left notifications', [
                'membership_id' => (string) $membership->id,
                'exception' => $e,
            ]);
        }
    }
}
