<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Enum\User\UserEmailType;
use App\Exception\User\EmailVerificationException;
use App\Repository\User\EmailVerificationCodeRepository;
use App\Service\Mail\Brevo\User\EmailVerificationCodeEmail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

readonly class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface          $entityManager,
        private EmailVerificationCodeRepository $repository,
        private EmailVerificationCodeEmail       $emailSender,
        private UserEmailLogService             $userEmailLogService,
        private EmailVerificationCodeGenerator   $codeGenerator,
        private PasswordHasherFactoryInterface   $passwordHasherFactory,
    ) {
    }

    public function generateAndSend(User $user): void
    {
        $plainCode = $this->codeGenerator->generate($user);

        $this->emailSender->send($user->getEmail(), $user->getUsername(), $plainCode);
        $this->userEmailLogService->log($user, UserEmailType::EMAIL_VERIFICATION_OTP);
    }

    /**
     * @throws EmailVerificationException
     */
    public function verify(User $user, string $code): void
    {
        $verificationCode = $this->repository->findLatestUnusedForUser($user);

        if ($verificationCode === null) {
            throw new EmailVerificationException('no_code_found');
        }

        if ($verificationCode->isExpired()) {
            throw new EmailVerificationException('code_expired');
        }

        if ($verificationCode->hasReachedMaxAttempts()) {
            throw new EmailVerificationException('max_attempts_reached');
        }

        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
        if (!$hasher->verify($verificationCode->hashedCode, $code)) {
            $verificationCode->attempts++;
            $this->entityManager->flush();

            if ($verificationCode->hasReachedMaxAttempts()) {
                throw new EmailVerificationException('max_attempts_reached');
            }

            throw new EmailVerificationException('invalid_code');
        }

        $verificationCode->usedDatetime = new \DateTimeImmutable();
        $user->setConfirmationDatetime(new \DateTime());
        $user->setToken(null);
        $this->entityManager->flush();
    }
}
