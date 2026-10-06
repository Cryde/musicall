<?php declare(strict_types=1);

namespace App\Service\Procedure\Moderation;

use App\Entity\Moderation\ModerationAction;
use App\Entity\Report\Report;
use App\Entity\User;
use App\Enum\Moderation\ModerationActionType;
use App\Enum\Report\ReportOutcome;
use App\Repository\Report\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * A moderator's decision on reported content. It closes every pending report on that content at once,
 * since the decision is about the content, not one report of it (#1116).
 */
readonly class ReportResolutionProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReportRepository $reportRepository,
        private AccountSuspensionProcedure $accountSuspension,
    ) {
    }

    public function dismiss(Report $report, User $moderator): void
    {
        // A decision already taken is not taken again, nor logged twice.
        if (!$report->isPending()) {
            throw new ConflictHttpException('Ce signalement est déjà traité');
        }

        $this->entityManager->wrapInTransaction(function () use ($report, $moderator): void {
            $this->reportRepository->resolvePendingForTarget($report->targetType, $report->targetId, $moderator, ReportOutcome::Dismissed);

            $action = new ModerationAction();
            $action->type = ModerationActionType::ReportDismissed;
            $action->moderator = $moderator;
            $action->targetUser = $report->targetAuthor;
            $action->report = $report;
            $this->entityManager->persist($action);
        });
    }

    public function suspendAuthor(Report $report, string $reason, User $moderator): void
    {
        $author = $report->targetAuthor;
        if (!$author instanceof User) {
            throw new UnprocessableEntityHttpException('Ce contenu n\'a pas d\'auteur à suspendre');
        }
        self::assertSuspendable($author, $moderator);

        $this->entityManager->wrapInTransaction(function () use ($report, $reason, $moderator, $author): void {
            if (!$author->isSuspended()) {
                $this->accountSuspension->suspend($author, $reason, $moderator, $report);
            }
            $this->reportRepository->resolvePendingForTarget($report->targetType, $report->targetId, $moderator, ReportOutcome::AccountSuspended);
        });
    }

    /** No moderator suspends themselves or another administrator. */
    public static function assertSuspendable(User $account, User $moderator): void
    {
        if ($account->id === $moderator->id || in_array('ROLE_ADMIN', $account->getRoles(), true)) {
            throw new AccessDeniedHttpException('Un administrateur ne peut pas être suspendu');
        }
    }
}
