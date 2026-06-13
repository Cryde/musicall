<?php

declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\EmailVerificationCheck;
use App\Event\UserConfirmedEvent;
use App\Exception\User\EmailVerificationException;
use App\Repository\UserRepository;
use App\Service\User\EmailVerificationService;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<EmailVerificationCheck, void>
 */
readonly class EmailVerificationCheckProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository           $userRepository,
        private EmailVerificationService $emailVerificationService,
        private EventDispatcherInterface $eventDispatcher,
        #[Target('email_verification_check')]
        private RateLimiterFactoryInterface $emailVerificationCheckLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        $this->emailVerificationCheckLimiter->create($ip)->consume()->ensureAccepted();

        $user = $this->userRepository->findOneBy(['email' => $data->email]);

        // Do not disclose whether the email is registered or already verified:
        // an unknown account and an already-verified one both return the same
        // generic "no pending code" response as the verify step, so this
        // endpoint cannot be used as an account-existence/verification oracle.
        if ($user === null || $user->confirmationDatetime !== null) {
            throw new BadRequestHttpException('no_code_found');
        }

        try {
            $this->emailVerificationService->verify($user, $data->code);
        } catch (EmailVerificationException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $this->eventDispatcher->dispatch(new UserConfirmedEvent($user));
    }
}
