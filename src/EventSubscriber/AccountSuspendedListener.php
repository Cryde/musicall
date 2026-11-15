<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\User\UserEmailType;
use App\Event\AccountSuspendedEvent;
use App\Service\Builder\Moderation\StatementOfReasonsBuilder;
use App\Service\Mail\Brevo\Moderation\StatementOfReasonsEmail;
use App\Service\User\UserEmailLogService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Emails the statement of reasons to the suspended account (DSA art. 17, terms §7.3, #1126): by email
 * because the account can no longer sign in to read anything else.
 */
#[AsEventListener]
readonly class AccountSuspendedListener
{
    public function __construct(
        private StatementOfReasonsBuilder $statementBuilder,
        private StatementOfReasonsEmail $statementEmail,
        private UserEmailLogService $userEmailLogService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AccountSuspendedEvent $event): void
    {
        $account = $event->account;
        // A closed account's address is anonymised: nobody would read it.
        if ($account->isDeleted()) {
            return;
        }

        try {
            $statement = $this->statementBuilder->build($event->action, $account->locale);
            $this->statementEmail->send($account->email, $account->username, $statement, $account->locale);
            $this->userEmailLogService->log($account, UserEmailType::ACCOUNT_SUSPENDED_STATEMENT, $event->action->id);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send the statement of reasons of a suspension', [
                'user_id' => $account->id,
                'moderation_action_id' => $event->action->id,
                'exception' => $e,
            ]);
        }
    }
}
