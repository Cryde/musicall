<?php

declare(strict_types=1);

namespace App\Repository\User;

use App\Entity\User;
use App\Entity\User\EmailVerificationCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailVerificationCode>
 */
class EmailVerificationCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailVerificationCode::class);
    }

    public function findLatestUnusedForUser(User $user): ?EmailVerificationCode
    {
        return $this->createQueryBuilder('evc')
            ->where('evc.user = :user')
            ->andWhere('evc.usedDatetime IS NULL')
            ->setParameter('user', $user)
            ->orderBy('evc.creationDatetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function invalidateAllForUser(User $user): void
    {
        $this->createQueryBuilder('evc')
            ->update()
            ->set('evc.usedDatetime', ':now')
            ->where('evc.user = :user')
            ->andWhere('evc.usedDatetime IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
