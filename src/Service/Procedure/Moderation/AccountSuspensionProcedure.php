<?php declare(strict_types=1);

namespace App\Service\Procedure\Moderation;

use App\Entity\Moderation\ModerationAction;
use App\Entity\Report\Report;
use App\Entity\User;
use App\Enum\Moderation\ModerationActionType;
use App\Enum\Moderation\ModerationBasis;
use App\Event\AccountSuspendedEvent;
use App\Event\SuspensionLiftedEvent;
use App\Repository\RefreshTokenRepository;
use App\Repository\User\DeviceTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/** Suspending an account and lifting it, each recorded (#1116) and told to the account (#1126). */
readonly class AccountSuspensionProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenRepository $refreshTokenRepository,
        private DeviceTokenRepository $deviceTokenRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function suspend(User $account, string $reason, ModerationBasis $basis, User $moderator): void
    {
        $action = $this->entityManager->wrapInTransaction(
            fn (): ModerationAction => $this->suspendInTransaction($account, $reason, $basis, $moderator),
        );

        $this->eventDispatcher->dispatch(new AccountSuspendedEvent($account, $action));
    }

    /**
     * For a caller that holds its own transaction: it dispatches AccountSuspendedEvent once that commits,
     * so the statement of reasons is never sent for a suspension rolled back with it.
     */
    public function suspendInTransaction(User $account, string $reason, ModerationBasis $basis, User $moderator, ?Report $report = null): ModerationAction
    {
        $account->suspensionDatetime = new \DateTimeImmutable();
        $account->suspensionReason = $reason;
        // Sessions end now; the JWT already issued is refused by SuspensionChecker. A Mercure
        // subscriber cookie taken before keeps working until it expires, an hour at most: accepted.
        $this->refreshTokenRepository->deleteForUsername($account->username);
        $this->deviceTokenRepository->deleteForUser($account);

        return $this->record(ModerationActionType::AccountSuspended, $moderator, $account, $reason, $basis, $report);
    }

    public function lift(User $account, User $moderator): void
    {
        $account->suspensionDatetime = null;
        $account->suspensionReason = null;
        $action = $this->record(ModerationActionType::SuspensionLifted, $moderator, $account);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new SuspensionLiftedEvent($account, $action));
    }

    private function record(
        ModerationActionType $type,
        User $moderator,
        User $account,
        ?string $reason = null,
        ?ModerationBasis $basis = null,
        ?Report $report = null,
    ): ModerationAction {
        $action = new ModerationAction();
        $action->type = $type;
        $action->moderator = $moderator;
        $action->targetUser = $account;
        $action->reason = $reason;
        $action->basis = $basis;
        $action->report = $report;
        $this->entityManager->persist($action);

        return $action;
    }
}
