<?php

namespace App\Repository\Musician;

use App\Entity\Musician\MusicianAnnounce;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MusicianAnnounce|null find($id, $lockMode = null, $lockVersion = null)
 * @method MusicianAnnounce|null findOneBy(array $criteria, array $orderBy = null)
 * @method MusicianAnnounce[]    findAll()
 * @method MusicianAnnounce[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MusicianAnnounceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MusicianAnnounce::class);
    }

    // /**
    //  * @return MusicianAnnounce[] Returns an array of MusicianAnnounce objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?MusicianAnnounce
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
