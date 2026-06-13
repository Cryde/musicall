<?php

declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\EmailVerificationSend;
use App\Exception\User\EmailVerificationException;
use App\Repository\UserRepository;
use App\Service\User\EmailVerificationService;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<EmailVerificationSend, void>
 */
readonly class EmailVerificationSendProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository           $userRepository,
        private EmailVerificationService $emailVerificationService,
        #[Target('email_verification_send')]
        private RateLimiterFactoryInterface $emailVerificationSendLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        $this->emailVerificationSendLimiter->create($ip)->consume()->ensureAccepted();

        $user = $this->userRepository->findOneBy(['email' => $data->email]);

        // Silent return to avoid email enumeration
        if ($user === null || $user->confirmationDatetime !== null) {
            return;
        }

        try {
            $this->emailVerificationService->generateAndSend($user);
        } catch (EmailVerificationException) {
            // Cooldown not expired: return silently (like the unknown/verified paths)
            // so the response stays uniform and cannot reveal that the account exists.
            return;
        }
    }
}
