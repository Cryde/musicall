<?php

declare(strict_types=1);

namespace App\Service\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Event\BandSpaceInvitationRespondedEvent;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\BandSpaceChangeSignal;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Adds a new account to the band spaces its email was invited to, whether it signed up with a password
 * or with Google or Apple (#1301).
 */
readonly class AcceptPendingInvitationsProcedure
{
    public function __construct(
        private BandSpaceInvitationRepository $invitationRepository,
        private BandSpaceMembershipRepository $membershipRepository,
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private BandSpaceChangeSignal $bandSpaceChangeSignal,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    /** Never throws: it runs inside sign-up, which an invitation must never break. */
    public function process(User $newUser): void
    {
        try {
            $this->acceptPendingInvitations($newUser);
        } catch (\Throwable $e) {
            // The activity queued a signal for a join that may never have been written.
            $this->bandSpaceChangeSignal->reset();
            $this->logger->error('Failed to accept the pending band space invitations of a new account', [
                'user_id' => (string) $newUser->id,
                'exception' => $e,
            ]);
        }
    }

    private function acceptPendingInvitations(User $user): void
    {
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

            if ($existingMembership instanceof BandSpaceMembership) {
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
                payload: $invitation->inviteeActivityPayload(),
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
