<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceEntrySplitCreate;
use App\ApiResource\BandSpace\Finance\FinanceEntrySplitResource;
use App\Entity\BandSpace\FinanceEntrySplit;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceEntrySplitRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\FinanceEntrySplitBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceEntrySplitCreate, FinanceEntrySplitResource>
 */
readonly class FinanceEntrySplitCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceEntryRepository $financeEntryRepository,
        private FinanceEntrySplitRepository $financeEntrySplitRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private FinanceEntrySplitBuilder $financeEntrySplitBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param FinanceEntrySplitCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FinanceEntrySplitResource
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->financeEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['entryId'], $bandSpace);
        if (!$entry) {
            throw new NotFoundHttpException('Entrée introuvable');
        }

        $member = $this->bandSpaceMembershipRepository->findOneByIdAndBandSpace($data->memberId, $bandSpace);
        if (!$member) {
            throw new NotFoundHttpException('Membre introuvable');
        }

        $existingSplit = $this->financeEntrySplitRepository->findOneByEntryAndMember($entry, $member);
        if ($existingSplit) {
            throw new ConflictHttpException('Une répartition existe déjà pour ce membre sur cette entrée');
        }

        if ($entry->amount !== null) {
            $currentTotal = $this->financeEntrySplitRepository->getSumByEntry($entry);
            if ($currentTotal + $data->amount > $entry->amount) {
                throw new BadRequestHttpException('Le total des répartitions dépasse le montant de l\'entrée');
            }
        }

        $split = new FinanceEntrySplit();
        $split->entry = $entry;
        $split->member = $member;
        $split->amount = $data->amount;

        $this->entityManager->persist($split);
        $this->entityManager->flush();

        return $this->financeEntrySplitBuilder->buildItem($split);
    }
}
