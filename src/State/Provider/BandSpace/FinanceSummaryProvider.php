<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Finance\FinanceSummary;
use App\Entity\User;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<FinanceSummary>
 */
readonly class FinanceSummaryProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private FinanceEntryRepository $financeEntryRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FinanceSummary
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $currentMembership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $from = isset($context['filters']['from']) ? new \DateTimeImmutable($context['filters']['from']) : null;
        $to = isset($context['filters']['to']) ? (new \DateTimeImmutable($context['filters']['to']))->modify('+1 day') : null;

        $boundaries = $this->financeEntryRepository->getDateBoundaries($bandSpace);
        $totals = $this->financeEntryRepository->getSummaryByBandSpace($bandSpace, $from, $to);
        $byCategory = $this->financeEntryRepository->getSummaryByCategory($bandSpace, $from, $to);
        $contributions = $this->financeEntryRepository->getMemberContributions($bandSpace, $from, $to);
        $upcoming = $this->financeEntryRepository->getUpcomingByBandSpace($bandSpace, $from, $to);

        $summary = new FinanceSummary();
        $summary->bandSpaceId = (string) $bandSpace->id;
        $summary->currentMembershipId = (string) $currentMembership->id;
        $summary->totalIncome = $totals['total_income'];
        $summary->totalExpense = $totals['total_expense'];
        $summary->totalIncomeAll = $totals['total_income_all'];
        $summary->totalExpenseAll = $totals['total_expense_all'];
        $summary->totalPlanned = $totals['total_planned'];
        $summary->totalCommitted = $totals['total_committed'];
        $summary->totalPaid = $totals['total_paid'];
        $summary->totalPersonal = $totals['total_personal'];
        $summary->hasEstimates = $totals['has_estimates'];
        $summary->minDate = $boundaries['min_date'];
        $summary->maxDate = $boundaries['max_date'];

        $summary->byCategory = array_map(fn (array $row): array => [
            'id' => $row['pole_id'],
            'name' => $row['pole_name'],
            'paid' => $row['paid'],
            'committed' => $row['committed'],
            'planned' => $row['planned'],
        ], $byCategory);

        $summary->memberContributions = array_map(fn (array $c): array => [
            'member_id' => $c['member_id'],
            'name' => $c['username'],
            'total' => $c['total'],
        ], $contributions);

        $summary->upcomingEntries = array_map(fn (\App\Entity\BandSpace\FinanceEntry $entry): array => [
            'id' => (string) $entry->id,
            'label' => $entry->label,
            'amount' => $entry->amount,
            'amount_min' => $entry->amountMin,
            'amount_max' => $entry->amountMax,
            'date' => $entry->date->format('Y-m-d'),
            'status' => $entry->status->value,
        ], $upcoming);

        return $summary;
    }
}
