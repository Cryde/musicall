<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Event\BandSpaceDeletionStateChangedEvent;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Cancels a scheduled deletion, as long as app:band-space:purge has not run yet.
 *
 * @implements ProcessorInterface<mixed, void>
 */
readonly class BandSpaceRestoreProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $user);

        if (!$bandSpace->deletionScheduledDatetime instanceof DateTimeImmutable) {
            throw new ConflictHttpException('La suppression de cet espace n\'est pas programmée');
        }

        $bandSpace->deletionScheduledDatetime = null;

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Settings,
            type: BandSpaceSettingsActivityType::DeletionCancelled,
            resourceId: (string) $bandSpace->id,
            actor: $user,
            payload: [],
        );

        $this->entityManager->flush();

        // Best-effort notification dispatched after the commit (epic #689 contract).
        $this->eventDispatcher->dispatch(new BandSpaceDeletionStateChangedEvent($bandSpace, $user, false));
    }
}
