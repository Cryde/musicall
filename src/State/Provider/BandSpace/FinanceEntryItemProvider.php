<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Finance\FinanceEntryResource;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\User;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceEntrySplitRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\FinanceEntryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<FinanceEntryResource>
 */
readonly class FinanceEntryItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private FinanceEntryRepository $financeEntryRepository,
        private FinanceEntrySplitRepository $financeEntrySplitRepository,
        private FinanceEntryBuilder $financeEntryBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FinanceEntryResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $viewer] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->financeEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);

        // Somebody else's personal entry answers exactly like an id that does not exist. The finder is
        // shared with the write paths, which need the entry in order to refuse the write with a reason,
        // so the read rule is applied here rather than in the query. A 404 rather than a 403 because a
        // 403 would confirm the entry exists, which is itself part of what is private about it.
        if (!$entry instanceof \App\Entity\BandSpace\FinanceEntry || !$this->isVisibleTo($entry, $viewer)) {
            throw new NotFoundHttpException('Entrée introuvable');
        }

        $splitWarning = false;
        if ($entry->amount !== null) {
            $splitSum = $this->financeEntrySplitRepository->getSumByEntry($entry);
            $splitWarning = $splitSum > 0 && $splitSum !== $entry->amount;
        }

        return $this->financeEntryBuilder->buildItem($entry, $splitWarning);
    }

    /**
     * The band's own entries are everybody's, a personal one is only its owner's. An ownerless
     * personal entry is nobody's: it should not exist, and hiding it is the safer reading of a fault.
     */
    private function isVisibleTo(FinanceEntry $entry, BandSpaceMembership $viewer): bool
    {
        if ($entry->scope !== FinanceEntryScope::Personal) {
            return true;
        }

        return $entry->member instanceof BandSpaceMembership && $entry->member->id === $viewer->id;
    }
}
