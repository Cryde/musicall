<?php

declare(strict_types=1);

namespace App\Repository\Teacher;

use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfileMedia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeacherProfileMedia>
 */
class TeacherProfileMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeacherProfileMedia::class);
    }

    public function getNextPosition(TeacherProfile $teacherProfile): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('MAX(m.position)')
            ->where('m.teacherProfile = :profile')
            ->setParameter('profile', $teacherProfile)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int) $result + 1 : 0;
    }

    public function countByTeacherProfile(string $teacherProfileId): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.teacherProfile = :profileId')
            ->setParameter('profileId', $teacherProfileId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
