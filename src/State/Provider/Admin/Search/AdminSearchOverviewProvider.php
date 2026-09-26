<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Search\AdminSearchCombination;
use App\ApiResource\Admin\Search\AdminSearchOverview;
use App\Repository\Search\MusicianSearchLogRepository;

/**
 * @implements ProviderInterface<AdminSearchOverview>
 */
readonly class AdminSearchOverviewProvider implements ProviderInterface
{
    private const int TOP_COMBINATIONS = 20;

    public function __construct(private MusicianSearchLogRepository $musicianSearchLogRepository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminSearchOverview
    {
        /** @var array{from: string, to: string} $filters */
        $filters = $context['filters'];
        $from = new \DateTimeImmutable($filters['from']);
        // +1 day so the end date is inclusive, as the dashboard reads it.
        $to = (new \DateTimeImmutable($filters['to']))->modify('+1 day');

        $summary = $this->musicianSearchLogRepository->summarizeBetween($from, $to);

        $overview = new AdminSearchOverview();
        $overview->from = $filters['from'];
        $overview->to = $filters['to'];
        $overview->searches = $summary['searches'];
        $overview->aiSearches = $summary['aiSearches'];
        $overview->zeroResultSearches = $summary['zeroResultSearches'];
        $overview->aiFilters = $summary['aiFilters'];
        $overview->aiNothing = $summary['aiNothing'];
        $overview->aiFailed = $summary['aiFailed'];
        $overview->topCombinations = array_map(static function (array $row): AdminSearchCombination {
            $combination = new AdminSearchCombination();
            $combination->type = $row['type'];
            $combination->instrumentName = $row['instrumentName'];
            $combination->locationName = $row['locationName'];
            $combination->searches = $row['searches'];
            $combination->visitors = $row['visitors'];
            $combination->zeroResults = $row['zeroResults'];

            return $combination;
        }, $this->musicianSearchLogRepository->findTopCombinationsBetween($from, $to, self::TOP_COMBINATIONS));

        return $overview;
    }
}
