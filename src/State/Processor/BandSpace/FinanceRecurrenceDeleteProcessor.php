<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFinanceActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileSourceDetacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceRecurrenceResource, void>
 */
readonly class FinanceRecurrenceDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceRecurrenceRepository $financeRecurrenceRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private BandSpaceFileSourceDetacher $fileSourceDetacher,
        private Security $security,
    ) {
    }

    /**
     * @param FinanceRecurrenceResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $recurrence = $this->financeRecurrenceRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$recurrence instanceof \App\Entity\BandSpace\FinanceRecurrence) {
            throw new NotFoundHttpException('Récurrence introuvable');
        }

        // One transaction, because the entries go out through a bulk DQL delete that reaches the
        // database immediately while the detached attachments wait for the flush. Unwrapped, a flush
        // that failed would leave the entries gone and their attachments behind, which is exactly the
        // orphan this change exists to stop.
        $this->entityManager->wrapInTransaction(function () use ($bandSpace, $recurrence, $user): void {
            $this->fileSourceDetacher->detachDeletedSources(
                $bandSpace,
                'finance',
                $this->financeEntryRepository->findPlannedLabelsByRecurrence($recurrence),
                $user,
            );
            $this->financeEntryRepository->deletePlannedByRecurrence($recurrence);

            $this->bandSpaceActivityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Finance,
                type: BandSpaceFinanceActivityType::RecurrenceDeleted,
                resourceId: $recurrence->id,
                actor: $user,
                payload: ['label' => $recurrence->label],
            );

            $this->entityManager->remove($recurrence);
        });
    }
}
