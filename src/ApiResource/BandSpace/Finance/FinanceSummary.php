<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\FinanceSummaryProvider;
use Symfony\Component\Validator\Constraints\Date;

#[Get(
    uriTemplate: '/band_spaces/{bandSpaceId}/finance/summary',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Finance']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    name: 'api_band_space_finance_summary',
    provider: FinanceSummaryProvider::class,
    parameters: [
        'from' => new QueryParameter(key: 'from', constraints: [new Date()]),
        'to' => new QueryParameter(key: 'to', constraints: [new Date()]),
    ],
)]
class FinanceSummary
{
    public string $bandSpaceId;
    public string $currentMembershipId;
    public int $totalIncome = 0;
    public int $totalExpense = 0;
    // *_all variants ignore the entry status (sum of paid + committed +
    // planned). Used by the dashboard widget so it reflects "everything
    // the user has logged this period" rather than only what's paid.
    public int $totalIncomeAll = 0;
    public int $totalExpenseAll = 0;
    public int $totalPlanned = 0;
    public int $totalCommitted = 0;
    public int $totalPaid = 0;
    public int $totalPersonal = 0;
    public bool $hasEstimates = false;
    public ?string $minDate = null;
    public ?string $maxDate = null;

    /** @var array<int, array{id: string, name: string, paid: int, committed: int, planned: int}> */
    public array $byCategory = [];

    /** @var array<int, array{member_id: string, name: string, total: int}> */
    public array $memberContributions = [];

    /** @var array<int, array{id: string, label: string, amount: ?int, amount_min: ?int, amount_max: ?int, date: ?string, status: string}> */
    public array $upcomingEntries = [];
}
