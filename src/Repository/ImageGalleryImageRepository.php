<?php

namespace App\Repository;

use App\Entity\ImageGalleryImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method ImageGalleryImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageGalleryImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method ImageGalleryImage[]    findAll()
 * @method ImageGalleryImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageGalleryImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageGalleryImage::class);
    }

    // /**
    //  * @return ImageGalleryImage[] Returns an array of ImageGalleryImage objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ImageGalleryImage
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
