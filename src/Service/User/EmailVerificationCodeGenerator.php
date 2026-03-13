<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Entity\User\EmailVerificationCode;
use App\Exception\User\EmailVerificationException;
use App\Repository\User\EmailVerificationCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

readonly class EmailVerificationCodeGenerator
{
    private const int CODE_LENGTH = 6;
    private const int EXPIRY_MINUTES = 15;
    private const int COOLDOWN_SECONDS = 60;

    public function __construct(
        private EntityManagerInterface          $entityManager,
        private EmailVerificationCodeRepository $repository,
        private PasswordHasherFactoryInterface   $passwordHasherFactory,
    ) {
    }

    /**
     * Creates a verification code for the user and returns the plain code.
     *
     * @throws EmailVerificationException if cooldown is not expired
     */
    public function generate(User $user): string
    {
        $latestCode = $this->repository->findLatestUnusedForUser($user);
        if ($latestCode !== null && !$this->isCooldownExpired($latestCode)) {
            throw new EmailVerificationException('cooldown_not_expired');
        }

        $this->repository->invalidateAllForUser($user);

        $plainCode = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        $verificationCode = new EmailVerificationCode();
        $verificationCode->user = $user;
        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
        $verificationCode->hashedCode = $hasher->hash($plainCode);
        $verificationCode->expirationDatetime = new \DateTimeImmutable(sprintf('+%d minutes', self::EXPIRY_MINUTES));

        $this->entityManager->persist($verificationCode);
        $this->entityManager->flush();

        return $plainCode;
    }

    private function isCooldownExpired(EmailVerificationCode $code): bool
    {
        $cooldownEnd = $code->creationDatetime->modify(sprintf('+%d seconds', self::COOLDOWN_SECONDS));

        return $cooldownEnd < new \DateTimeImmutable();
    }
}
