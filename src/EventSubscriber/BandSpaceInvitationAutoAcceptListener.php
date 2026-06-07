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

        $acceptedInvitations = [];
        foreach ($pendingInvitations as $invitation) {
            if ($this->membershipRepository->isMember($invitation->bandSpace, $user)) {
                continue;
            }

            $existingMembership = $this->membershipRepository->findMembershipIncludingInactive($invitation->bandSpace, $user);

            if ($existingMembership instanceof \App\Entity\BandSpace\BandSpaceMembership) {
                $existingMembership->status = MembershipStatus::Active;
                $existingMembership->leftDatetime = null;
                $existingMembership->role = Role::User;
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

        // Best-effort notifications dispatched after the commit (epic #689 contract): tell each inviter
        // their invitation was accepted, exactly as the explicit accept endpoint does.
        foreach ($acceptedInvitations as $invitation) {
            $this->eventDispatcher->dispatch(
                new BandSpaceInvitationRespondedEvent($invitation, $user, InvitationStatus::Accepted),
            );
        }
    }
}
