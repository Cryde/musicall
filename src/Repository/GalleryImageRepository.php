<?php

namespace App\Repository;

use App\Entity\GalleryImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method GalleryImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method GalleryImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method GalleryImage[]    findAll()
 * @method GalleryImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GalleryImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryImage::class);
    }

    // /**
    //  * @return GalleryImage[] Returns an array of GalleryImage objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('g.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?GalleryImage
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
