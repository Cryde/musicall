<?php declare(strict_types=1);

namespace App\Service\Builder\Admin\Report;

use App\ApiResource\Admin\Report\AdminReport;
use App\Entity\Report\Report;
use App\Entity\User;
use App\Repository\Report\ReportRepository;
use App\Service\Report\ReportTarget;
use App\Service\Report\Target\ReportTargetLoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class AdminReportBuilder
{
    /** @var array<string, ReportTargetLoaderInterface> */
    private array $loaders;

    /**
     * @param iterable<ReportTargetLoaderInterface> $loaders
     */
    public function __construct(
        private ReportRepository $reportRepository,
        #[AutowireIterator('app.report_target_loader')]
        iterable $loaders,
    ) {
        $byType = [];
        foreach ($loaders as $loader) {
            $byType[$loader->type()->value] = $loader;
        }
        $this->loaders = $byType;
    }

    /**
     * @param list<Report> $reports
     *
     * @return list<AdminReport>
     */
    public function buildList(array $reports): array
    {
        $counts = $this->reportRepository->countPendingPerTarget($reports);

        return array_map(fn (Report $report): AdminReport => $this->build($report, $counts), $reports);
    }

    public function buildDetail(Report $report): AdminReport
    {
        $resource = $this->build($report, $this->reportRepository->countPendingPerTarget([$report]));
        $resource->liveState = $this->liveState($report);

        return $resource;
    }

    /**
     * @param array<string, int> $counts
     */
    private function build(Report $report, array $counts): AdminReport
    {
        $resource = new AdminReport();
        $resource->id = (string) $report->id;
        $resource->targetType = $report->targetType->value;
        $resource->targetId = $report->targetId;
        $resource->reason = $report->reason->value;
        $resource->details = $report->details;
        $resource->snapshotText = $report->snapshotText;
        $resource->snapshotContext = $report->snapshotContext;
        $resource->reporter = ['id' => $report->reporter->id, 'username' => $report->reporter->username];
        $resource->targetAuthor = $report->targetAuthor instanceof User
            ? [
                'id' => $report->targetAuthor->id,
                'username' => $report->targetAuthor->username,
                'is_suspended' => $report->targetAuthor->isSuspended(),
                // An administrator cannot be suspended, so the action can be disabled up front.
                'is_admin' => in_array('ROLE_ADMIN', $report->targetAuthor->getRoles(), true),
            ]
            : null;
        $resource->pendingReportCount = $counts[$report->targetType->value . '|' . $report->targetId] ?? 0;
        $resource->creationDatetime = $report->creationDatetime;
        $resource->resolutionDatetime = $report->resolutionDatetime;
        $resource->resolvedByUsername = $report->resolvedBy?->username;
        $resource->outcome = $report->outcome?->value;

        return $resource;
    }

    private function liveState(Report $report): string
    {
        $current = ($this->loaders[$report->targetType->value] ?? null)?->current($report->targetId);
        if (!$current instanceof ReportTarget) {
            return AdminReport::LIVE_REMOVED;
        }

        return $current->text === $report->snapshotText ? AdminReport::LIVE_UNCHANGED : AdminReport::LIVE_EDITED;
    }
}
