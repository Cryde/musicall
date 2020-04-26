<?php

namespace App\Repository;

use App\Entity\PublicationFeatured;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PublicationFeatured|null find($id, $lockMode = null, $lockVersion = null)
 * @method PublicationFeatured|null findOneBy(array $criteria, array $orderBy = null)
 * @method PublicationFeatured[]    findAll()
 * @method PublicationFeatured[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PublicationFeaturedRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationFeatured::class);
    }
}
