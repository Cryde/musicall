<?php

declare(strict_types=1);

namespace App\Repository\Teacher;

use App\Entity\Teacher\TeacherProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeacherProfile>
 */
class TeacherProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeacherProfile::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('tp')
            ->select('COUNT(tp.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<TeacherProfile>
     */
    public function findRecentTeachers(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array
    {
        return $this->createQueryBuilder('tp')
            ->innerJoin('tp.user', 'u')
            ->addSelect('u')
            ->where('tp.creationDatetime >= :from')
            ->andWhere('tp.creationDatetime < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('tp.creationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByUsername(string $username): ?TeacherProfile
    {
        return $this->createQueryBuilder('tp')
            ->innerJoin('tp.user', 'u')
            ->where('u.username = :username')
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
