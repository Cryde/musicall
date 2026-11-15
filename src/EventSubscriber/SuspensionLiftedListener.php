<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\User\UserEmailType;
use App\Event\SuspensionLiftedEvent;
use App\Service\Mail\Brevo\Moderation\SuspensionLiftedEmail;
use App\Service\User\UserEmailLogService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Tells the account it can sign in again (#1126). */
#[AsEventListener]
readonly class SuspensionLiftedListener
{
    public function __construct(
        private SuspensionLiftedEmail $suspensionLiftedEmail,
        private UserEmailLogService $userEmailLogService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SuspensionLiftedEvent $event): void
    {
        $account = $event->account;
        if ($account->isDeleted()) {
            return;
        }

        try {
            $this->suspensionLiftedEmail->send($account->email, $account->username, $event->action->creationDatetime, $account->locale);
            $this->userEmailLogService->log($account, UserEmailType::SUSPENSION_LIFTED, $event->action->id);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send the suspension lifted email', [
                'user_id' => $account->id,
                'moderation_action_id' => $event->action->id,
                'exception' => $e,
            ]);
        }
    }
}
