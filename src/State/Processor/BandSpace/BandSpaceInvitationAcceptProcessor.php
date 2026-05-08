<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Invitation\BandSpaceInvitationAccept;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, BandSpaceInvitationAccept>
 */
readonly class BandSpaceInvitationAcceptProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceInvitationRepository $bandSpaceInvitationRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceInvitationAccept
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $invitation = $this->bandSpaceInvitationRepository->findPendingByToken((string) $uriVariables['token']);
        if (!$invitation) {
            throw new NotFoundHttpException('Invitation introuvable ou expirée');
        }

        if ($invitation->existingUser !== null) {
            if ($invitation->existingUser->id !== $user->id) {
                throw new AccessDeniedHttpException('Cette invitation ne vous est pas destinée');
            }
        } else {
            if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
                throw new AccessDeniedHttpException('Cette invitation ne vous est pas destinée');
            }
        }

        if ($this->bandSpaceMembershipRepository->isMember($invitation->bandSpace, $user)) {
            throw new ConflictHttpException('Vous êtes déjà membre de ce Band Space');
        }

        $existingMembership = $this->bandSpaceMembershipRepository->findMembershipIncludingInactive($invitation->bandSpace, $user);

        if ($existingMembership) {
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

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Vous êtes déjà membre de ce Band Space');
        }

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
        $this->entityManager->flush();

        $dto = new BandSpaceInvitationAccept();
        $dto->token = $invitation->token;
        $dto->bandSpaceId = (string) $invitation->bandSpace->id;
        $dto->bandSpaceName = $invitation->bandSpace->name;

        return $dto;
    }
}
