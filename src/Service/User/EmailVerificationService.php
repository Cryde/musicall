<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Entity\User\EmailVerificationCode;
use App\Enum\User\UserEmailType;
use App\Exception\User\EmailVerificationException;
use App\Repository\User\EmailVerificationCodeRepository;
use App\Service\Mail\Brevo\User\EmailVerificationCodeEmail;
use App\Service\User\UserEmailLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

readonly class EmailVerificationService
{
    private const int CODE_LENGTH = 6;
    private const int EXPIRY_MINUTES = 15;
    private const int COOLDOWN_SECONDS = 60;

    public function __construct(
        private EntityManagerInterface          $entityManager,
        private EmailVerificationCodeRepository $repository,
        private EmailVerificationCodeEmail       $emailSender,
        private UserEmailLogService             $userEmailLogService,
        private PasswordHasherFactoryInterface   $passwordHasherFactory,
    ) {
    }

    public function generateAndSend(User $user): void
    {
        $latestCode = $this->repository->findLatestUnusedForUser($user);
        if ($latestCode !== null && !$this->isCooldownExpired($latestCode)) {
            throw new EmailVerificationException('cooldown_not_expired');
        }

        $this->repository->invalidateAllForUser($user);

        $plainCode = $this->generateCode();

        $verificationCode = new EmailVerificationCode();
        $verificationCode->user = $user;
        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
        $verificationCode->hashedCode = $hasher->hash($plainCode);
        $verificationCode->expirationDatetime = new \DateTimeImmutable(sprintf('+%d minutes', self::EXPIRY_MINUTES));

        $this->entityManager->persist($verificationCode);
        $this->entityManager->flush();

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

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function isCooldownExpired(EmailVerificationCode $code): bool
    {
        $cooldownEnd = $code->creationDatetime->modify(sprintf('+%d seconds', self::COOLDOWN_SECONDS));

        return $cooldownEnd < new \DateTimeImmutable();
    }
}
