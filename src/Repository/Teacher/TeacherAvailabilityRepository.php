<?php

declare(strict_types=1);

namespace App\Repository\Teacher;

use App\Entity\Teacher\TeacherAvailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeacherAvailability>
 */
class TeacherAvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeacherAvailability::class);
    }
}
