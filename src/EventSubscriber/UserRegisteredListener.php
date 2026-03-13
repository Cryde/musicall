<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use App\Service\User\EmailVerificationService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class UserRegisteredListener
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
    ) {
    }

    public function __invoke(UserRegisteredEvent $event): void
    {
        $this->emailVerificationService->generateAndSend($event->user);
    }
}
