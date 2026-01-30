<?php

declare(strict_types=1);

namespace App\Repository\Teacher;

use App\Entity\Teacher\TeacherProfilePricing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeacherProfilePricing>
 */
class TeacherProfilePricingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeacherProfilePricing::class);
    }
}
