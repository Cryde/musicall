<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Finance\FinanceEntrySplitResource;
use App\Entity\User;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceEntrySplitRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\FinanceEntrySplitBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<FinanceEntrySplitResource>
 */
readonly class FinanceEntrySplitItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private FinanceEntryRepository $financeEntryRepository,
        private FinanceEntrySplitRepository $financeEntrySplitRepository,
        private FinanceEntrySplitBuilder $financeEntrySplitBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FinanceEntrySplitResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->financeEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['entryId'], $bandSpace);
        if (!$entry) {
            throw new NotFoundHttpException('Entrée introuvable');
        }

        $split = $this->financeEntrySplitRepository->findOneByIdAndEntry((string) $uriVariables['id'], $entry);
        if (!$split) {
            throw new NotFoundHttpException('Répartition introuvable');
        }

        return $this->financeEntrySplitBuilder->buildItem($split);
    }
}
