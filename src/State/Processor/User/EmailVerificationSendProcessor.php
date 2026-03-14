<?php

declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\EmailVerificationSend;
use App\Exception\User\EmailVerificationException;
use App\Repository\UserRepository;
use App\Service\User\EmailVerificationService;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * @implements ProcessorInterface<EmailVerificationSend, void>
 */
readonly class EmailVerificationSendProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository           $userRepository,
        private EmailVerificationService $emailVerificationService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->userRepository->findOneBy(['email' => $data->email]);

        // Silent return to avoid email enumeration
        if ($user === null || $user->confirmationDatetime !== null) {
            return;
        }

        try {
            $this->emailVerificationService->generateAndSend($user);
        } catch (EmailVerificationException) {
            throw new TooManyRequestsHttpException(60, 'Veuillez patienter avant de renvoyer un code.');
        }
    }
}
