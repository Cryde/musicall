<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceEntrySplitResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFinanceActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceEntrySplitRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<FinanceEntrySplitResource, void>
 */
readonly class FinanceEntrySplitDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceEntryRepository $financeEntryRepository,
        private FinanceEntrySplitRepository $financeEntrySplitRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param FinanceEntrySplitResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->financeEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['entryId'], $bandSpace);
        if (!$entry instanceof \App\Entity\BandSpace\FinanceEntry) {
            throw new NotFoundHttpException('Entrée introuvable');
        }

        if ($entry->status === FinanceEntryStatus::Paid) {
            throw new UnprocessableEntityHttpException('Impossible de supprimer une répartition d\'une entrée payée. Repassez le statut à Engagé.');
        }

        $split = $this->financeEntrySplitRepository->findOneByIdAndEntry((string) $uriVariables['id'], $entry);
        if (!$split instanceof \App\Entity\BandSpace\FinanceEntrySplit) {
            throw new NotFoundHttpException('Répartition introuvable');
        }

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Finance,
            type: BandSpaceFinanceActivityType::SplitRemoved,
            resourceId: $entry->id,
            actor: $user,
            payload: [
                'split_id' => (string) $split->id,
                'member_id' => $split->member?->id,
                'member_username' => $split->member?->user->username,
                'amount' => $split->amount,
            ],
        );

        $this->entityManager->remove($split);
        $this->entityManager->flush();
    }
}
