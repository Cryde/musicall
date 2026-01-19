<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Entity\User\UserEmailLog;
use App\Enum\User\UserEmailType;
use App\Repository\User\UserEmailLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class UserEmailLogService
{
    public function __construct(
        private UserEmailLogRepository $userEmailLogRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function hasBeenSent(User $user, UserEmailType $emailType, ?string $referenceId = null): bool
    {
        return $this->userEmailLogRepository->findOneByUserAndType($user, $emailType, $referenceId) !== null;
    }

    public function hasBeenSentSince(
        User $user,
        UserEmailType $emailType,
        DateTimeImmutable $since,
        ?string $referenceId = null
    ): bool {
        return $this->userEmailLogRepository->findOneByUserAndTypeSince($user, $emailType, $since, $referenceId) !== null;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function log(User $user, UserEmailType $emailType, ?string $referenceId = null, ?array $metadata = null): void
    {
        $log = new UserEmailLog();
        $log->setUser($user);
        $log->setEmailType($emailType);
        $log->setReferenceId($referenceId);
        $log->setMetadata($metadata);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function countSent(User $user, UserEmailType $emailType): int
    {
        return $this->userEmailLogRepository->countByUserAndType($user, $emailType);
    }
}
