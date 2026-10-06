<?php declare(strict_types=1);

namespace App\State\Provider\Admin\Report;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Report\AdminReport;
use App\Repository\Report\ReportRepository;
use App\Service\Builder\Admin\Report\AdminReportBuilder;
use ArrayIterator;

/**
 * @implements ProviderInterface<AdminReport>
 */
readonly class AdminReportCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ReportRepository $reportRepository,
        private AdminReportBuilder $builder,
        private Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $status = $context['filters']['status'] ?? null;
        $pending = match ($status) {
            AdminReport::STATUS_PENDING => true,
            AdminReport::STATUS_RESOLVED => false,
            default => null,
        };

        $paginator = $this->reportRepository->findForAdmin($pending, $offset, $itemsPerPage);
        $resources = $this->builder->buildList(array_values(iterator_to_array($paginator)));

        return new TraversablePaginator(new ArrayIterator($resources), $page, $itemsPerPage, count($paginator));
    }
}
