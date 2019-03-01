<?php

namespace App\Repository;

use App\Entity\PublicationSubCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method PublicationSubCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method PublicationSubCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method PublicationSubCategory[]    findAll()
 * @method PublicationSubCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PublicationSubCategoryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, PublicationSubCategory::class);
    }
}
