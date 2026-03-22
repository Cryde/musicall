<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\Role;
use App\Event\UserRegisteredEvent;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class BandSpaceInvitationAutoAcceptListener
{
    public function __construct(
        private BandSpaceInvitationRepository $invitationRepository,
        private BandSpaceMembershipRepository $membershipRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(UserRegisteredEvent $event): void
    {
        $user = $event->user;
        $pendingInvitations = $this->invitationRepository->findPendingByEmail($user->email);

        if (empty($pendingInvitations)) {
            return;
        }

        foreach ($pendingInvitations as $invitation) {
            if ($this->membershipRepository->isMember($invitation->bandSpace, $user)) {
                continue;
            }

            $membership = new BandSpaceMembership();
            $membership->bandSpace = $invitation->bandSpace;
            $membership->user = $user;
            $membership->role = Role::User;

            $invitation->status = InvitationStatus::Accepted;
            $invitation->existingUser = $user;

            $this->entityManager->persist($membership);
        }

        $this->entityManager->flush();
    }
}
