<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Finance\FinanceEntryResource;
use App\Entity\User;
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

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->financeEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$entry) {
            throw new NotFoundHttpException('Entrée introuvable');
        }

        $splitWarning = false;
        if ($entry->amount !== null) {
            $splitSum = $this->financeEntrySplitRepository->getSumByEntry($entry);
            $splitWarning = $splitSum > 0 && $splitSum !== $entry->amount;
        }

        return $this->financeEntryBuilder->buildItem($entry, $splitWarning);
    }
}
