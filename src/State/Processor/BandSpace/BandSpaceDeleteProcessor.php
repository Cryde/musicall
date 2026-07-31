<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpace as BandSpaceResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Event\BandSpaceDeletionStateChangedEvent;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Schedules a band space for deletion instead of deleting it. The space stays listed and usable during
 * the grace period so members can still download their files; app:band-space:purge removes the rows and
 * the stored objects once the due date has passed, and BandSpaceRestoreProcessor cancels it before then.
 *
 * @implements ProcessorInterface<BandSpaceResource, void>
 */
readonly class BandSpaceDeleteProcessor implements ProcessorInterface
{
    /**
     * Kept in sync with the durations announced in the privacy policy and the terms.
     */
    private const int GRACE_PERIOD_DAYS = 30;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param BandSpaceResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['id'], $user);

        if ($bandSpace->deletionScheduledDatetime instanceof DateTimeImmutable) {
            throw new ConflictHttpException('La suppression de cet espace est déjà programmée');
        }

        $scheduledFor = new DateTimeImmutable(sprintf('+%d days', self::GRACE_PERIOD_DAYS));
        $bandSpace->deletionScheduledDatetime = $scheduledFor;

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Settings,
            type: BandSpaceSettingsActivityType::DeletionScheduled,
            resourceId: (string) $bandSpace->id,
            actor: $user,
            payload: ['scheduled_for' => $scheduledFor->format(DateTimeInterface::ATOM)],
        );

        $this->entityManager->flush();

        // Best-effort notification dispatched after the commit (epic #689 contract).
        $this->eventDispatcher->dispatch(new BandSpaceDeletionStateChangedEvent($bandSpace, $user, true));
    }
}
