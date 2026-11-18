<?php

declare(strict_types=1);

namespace App\Repository\Service;

use App\Entity\Service\ServiceWork;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceWork>
 */
class ServiceWorkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceWork::class);
    }
}
