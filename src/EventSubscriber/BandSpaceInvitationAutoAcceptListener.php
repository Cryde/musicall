<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Event\BandSpaceInvitationRespondedEvent;
use App\Event\UserRegisteredEvent;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener]
readonly class BandSpaceInvitationAutoAcceptListener
{
    public function __construct(
        private BandSpaceInvitationRepository $invitationRepository,
        private BandSpaceMembershipRepository $membershipRepository,
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(UserRegisteredEvent $event): void
    {
        $user = $event->user;
        $pendingInvitations = $this->invitationRepository->findPendingByEmail($user->email);

        if ($pendingInvitations === []) {
            return;
        }

        $rejoinedSpaces = [];
        $acceptedInvitations = [];
        foreach ($pendingInvitations as $invitation) {
            if ($this->membershipRepository->isMember($invitation->bandSpace, $user)) {
                continue;
            }

            // Symmetry with BandSpaceInvitationAcceptProcessor, which rejects joining a condemned space.
            // Skipped silently rather than thrown: this runs inside registration and must never break it.
            // The invitation stays pending and gets purged with the space.
            if ($invitation->bandSpace->isPendingDeletion()) {
                continue;
            }

            $existingMembership = $this->membershipRepository->findMembershipIncludingInactive($invitation->bandSpace, $user);

            if ($existingMembership instanceof \App\Entity\BandSpace\BandSpaceMembership) {
                $existingMembership->status = MembershipStatus::Active;
                $existingMembership->leftDatetime = null;
                $existingMembership->role = Role::User;
                $rejoinedSpaces[] = $invitation->bandSpace;
            } else {
                $existingMembership = new BandSpaceMembership();
                $existingMembership->bandSpace = $invitation->bandSpace;
                $existingMembership->user = $user;
                $existingMembership->role = Role::User;

                $this->entityManager->persist($existingMembership);
            }

            $invitation->status = InvitationStatus::Accepted;
            $invitation->existingUser = $user;

            $this->bandSpaceActivityRecorder->record(
                bandSpace: $invitation->bandSpace,
                module: BandSpaceModule::Settings,
                type: BandSpaceSettingsActivityType::InvitationAccepted,
                resourceId: $invitation->id,
                actor: $user,
                payload: [
                    'email' => $invitation->email,
                    'invited_user_id' => $user->id,
                    'invited_username' => $user->username,
                ],
            );

            $acceptedInvitations[] = $invitation;
        }

        $this->entityManager->flush();

        // Same rule as the accept endpoint, and after the flush for the same reason: a rejoining
        // member reuses their old membership row and its old read position, which would present
        // everything said while they were gone as unread (#962). One flush covers the whole loop, so
        // resetting inside it would commit for invitations that never persisted.
        foreach ($rejoinedSpaces as $bandSpace) {
            $this->messageThreadMetaRepository->markChannelsReadForUser($bandSpace, $user);
        }

        // Best-effort notifications dispatched after the commit (epic #689 contract): tell each inviter
        // their invitation was accepted, exactly as the explicit accept endpoint does.
        foreach ($acceptedInvitations as $invitation) {
            $this->eventDispatcher->dispatch(
                new BandSpaceInvitationRespondedEvent($invitation, $user, InvitationStatus::Accepted),
            );
        }
    }
}
