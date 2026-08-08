<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\PersonalRecurrenceDeactivator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, void>
 */
readonly class BandSpaceLeaveProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private PersonalRecurrenceDeactivator $personalRecurrenceDeactivator,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $bandSpace = $this->bandSpaceRepository->findOneByIdWithMemberships((string) $uriVariables['bandSpaceId']);
        if (!$bandSpace instanceof \App\Entity\BandSpace\BandSpace) {
            throw new NotFoundHttpException('Band Space introuvable');
        }

        $membership = $this->bandSpaceMembershipRepository->findMembership($bandSpace, $user);
        if (!$membership instanceof \App\Entity\BandSpace\BandSpaceMembership) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas membre de ce Band Space');
        }

        if ($membership->role === Role::Admin && $this->bandSpaceMembershipRepository->countAdmins($bandSpace) === 1) {
            throw new ConflictHttpException('Vous devez promouvoir un autre membre administrateur avant de quitter');
        }

        // One transaction: deactivating the personal recurrences drops their planned entries through a
        // bulk DQL delete that reaches the database immediately, while the membership and the files
        // detached alongside it wait for the flush. Unwrapped, a flush that failed would leave the
        // entries gone, their attachments orphaned and the member still in the band.
        $this->entityManager->wrapInTransaction(function () use ($bandSpace, $membership, $user): void {
            $membership->status = MembershipStatus::Left;
            $membership->leftDatetime = new DateTime();

            $this->personalRecurrenceDeactivator->deactivateForMember($membership, $user);

            $this->bandSpaceActivityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Settings,
                type: BandSpaceSettingsActivityType::MemberLeft,
                resourceId: $user->id,
                actor: $user,
                payload: [
                    'target_user_id' => $user->id,
                    'target_username' => $user->username,
                ],
            );
        });
    }
}
