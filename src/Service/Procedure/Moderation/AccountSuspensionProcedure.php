<?php declare(strict_types=1);

namespace App\Service\Procedure\Moderation;

use App\Entity\Moderation\ModerationAction;
use App\Entity\Report\Report;
use App\Entity\User;
use App\Enum\Moderation\ModerationActionType;
use App\Repository\RefreshTokenRepository;
use App\Repository\User\DeviceTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Suspending an account and lifting it, each recorded (#1116). */
readonly class AccountSuspensionProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenRepository $refreshTokenRepository,
        private DeviceTokenRepository $deviceTokenRepository,
    ) {
    }

    public function suspend(User $account, string $reason, User $moderator, ?Report $report = null): void
    {
        $this->entityManager->wrapInTransaction(function () use ($account, $reason, $moderator, $report): void {
            $account->suspensionDatetime = new \DateTimeImmutable();
            $account->suspensionReason = $reason;
            // Sessions end now; the JWT already issued is refused by SuspensionChecker. A Mercure
            // subscriber cookie taken before keeps working until it expires, an hour at most: accepted.
            $this->refreshTokenRepository->deleteForUsername($account->username);
            $this->deviceTokenRepository->deleteForUser($account);

            $this->record(ModerationActionType::AccountSuspended, $moderator, $account, $reason, $report);
        });
    }

    public function lift(User $account, User $moderator): void
    {
        $account->suspensionDatetime = null;
        $account->suspensionReason = null;
        $this->record(ModerationActionType::SuspensionLifted, $moderator, $account, null, null);
        $this->entityManager->flush();
    }

    private function record(ModerationActionType $type, User $moderator, User $account, ?string $reason, ?Report $report): void
    {
        $action = new ModerationAction();
        $action->type = $type;
        $action->moderator = $moderator;
        $action->targetUser = $account;
        $action->reason = $reason;
        $action->report = $report;
        $this->entityManager->persist($action);
    }
}
