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

/**
 * @implements ProviderInterface<object>
 */
readonly class FinanceEntryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private FinanceEntryRepository $financeEntryRepository,
        private FinanceEntrySplitRepository $financeEntrySplitRepository,
        private FinanceEntryBuilder $financeEntryBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return FinanceEntryResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $from = isset($context['filters']['from']) ? new \DateTimeImmutable($context['filters']['from']) : null;
        $to = isset($context['filters']['to']) ? (new \DateTimeImmutable($context['filters']['to']))->modify('+1 day') : null;

        $entries = $this->financeEntryRepository->findByBandSpace($bandSpace, $from, $to);

        $entriesWithAmount = array_filter($entries, fn (\App\Entity\BandSpace\FinanceEntry $e): bool => $e->amount !== null);
        $splitSums = $this->financeEntrySplitRepository->getSumsByEntries($entriesWithAmount);

        $splitWarnings = [];
        foreach ($entriesWithAmount as $entry) {
            $splitSum = $splitSums[(string) $entry->id] ?? 0;
            $splitWarnings[(string) $entry->id] = $splitSum > 0 && $splitSum !== $entry->amount;
        }

        return $this->financeEntryBuilder->buildFromList($entries, $splitWarnings);
    }
}
