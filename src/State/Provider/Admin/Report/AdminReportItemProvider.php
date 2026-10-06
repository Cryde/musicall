<?php declare(strict_types=1);

namespace App\State\Provider\Admin\Report;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Report\AdminReport;
use App\Entity\Report\Report;
use App\Repository\Report\ReportRepository;
use App\Service\Builder\Admin\Report\AdminReportBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<AdminReport>
 */
readonly class AdminReportItemProvider implements ProviderInterface
{
    public function __construct(
        private ReportRepository $reportRepository,
        private AdminReportBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminReport
    {
        $report = $this->reportRepository->findOneById((string) $uriVariables['id']);
        if (!$report instanceof Report) {
            throw new NotFoundHttpException('Signalement introuvable');
        }

        return $this->builder->buildDetail($report);
    }
}
