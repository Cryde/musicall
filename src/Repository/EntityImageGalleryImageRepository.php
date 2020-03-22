<?php

namespace App\Repository;

use App\Entity\EntityImageGalleryImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method EntityImageGalleryImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method EntityImageGalleryImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method EntityImageGalleryImage[]    findAll()
 * @method EntityImageGalleryImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntityImageGalleryImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityImageGalleryImage::class);
    }

    // /**
    //  * @return EntityImageGalleryImage[] Returns an array of EntityImageGalleryImage objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?EntityImageGalleryImage
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
