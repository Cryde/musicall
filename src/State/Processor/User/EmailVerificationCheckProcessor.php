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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->userRepository->findOneBy(['email' => $data->email]);

        if ($user === null) {
            throw new BadRequestHttpException('no_code_found');
        }

        if ($user->confirmationDatetime !== null) {
            throw new BadRequestHttpException('already_verified');
        }

        try {
            $this->emailVerificationService->verify($user, $data->code);
        } catch (EmailVerificationException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $this->eventDispatcher->dispatch(new UserConfirmedEvent($user));
    }
}
