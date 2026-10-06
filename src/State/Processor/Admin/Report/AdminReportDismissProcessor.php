<?php declare(strict_types=1);

namespace App\State\Processor\Admin\Report;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Report\Report;
use App\Entity\User;
use App\Repository\Report\ReportRepository;
use App\Service\Procedure\Moderation\ReportResolutionProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<null, null>
 */
readonly class AdminReportDismissProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ReportRepository $reportRepository,
        private ReportResolutionProcedure $resolution,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $moderator = $this->security->getUser();
        if (!$moderator instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $report = $this->reportRepository->findOneById((string) $uriVariables['id']);
        if (!$report instanceof Report) {
            throw new NotFoundHttpException('Signalement introuvable');
        }

        $this->resolution->dismiss($report, $moderator);

        return null;
    }
}
