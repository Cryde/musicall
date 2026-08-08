<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceMember;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\MembershipStatus;
use App\Event\BandSpaceMemberRemovedEvent;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\PersonalRecurrenceDeactivator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<BandSpaceMember, void>
 */
readonly class BandSpaceMemberDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private PersonalRecurrenceDeactivator $personalRecurrenceDeactivator,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param BandSpaceMember $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->adminChecker->checkAdminForWrite((string) $uriVariables['bandSpaceId'], $user);

        $membership = $this->bandSpaceMembershipRepository->findOneByIdAndBandSpace(
            (string) $uriVariables['id'],
            $bandSpace
        );

        if (!$membership instanceof \App\Entity\BandSpace\BandSpaceMembership) {
            throw new NotFoundHttpException('Membre introuvable');
        }

        if ($membership->user->id === $user->id) {
            throw new ConflictHttpException('Vous ne pouvez pas vous exclure vous-même. Utilisez la fonction "Quitter"');
        }

        $membership->status = MembershipStatus::Kicked;
        $membership->leftDatetime = new DateTime();

        $this->personalRecurrenceDeactivator->deactivateForMember($membership);

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Settings,
            type: BandSpaceSettingsActivityType::MemberRemoved,
            resourceId: $membership->user->id,
            actor: $user,
            payload: [
                'target_user_id' => $membership->user->id,
                'target_username' => $membership->user->username,
            ],
        );

        $this->entityManager->flush();

        // Best-effort notification dispatched after the commit (epic #689 contract).
        $this->eventDispatcher->dispatch(new BandSpaceMemberRemovedEvent($membership, $user));
    }
}
