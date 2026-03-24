<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceEntrySplitResource;
use App\Entity\User;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceEntrySplitRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        if (!$entry) {
            throw new NotFoundHttpException('Entrée introuvable');
        }

        $split = $this->financeEntrySplitRepository->findOneByIdAndEntry((string) $uriVariables['id'], $entry);
        if (!$split) {
            throw new NotFoundHttpException('Répartition introuvable');
        }

        $this->entityManager->remove($split);
        $this->entityManager->flush();
    }
}
