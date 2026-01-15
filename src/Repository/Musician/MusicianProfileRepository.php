<?php

declare(strict_types=1);

namespace App\Repository\Musician;

use App\Entity\Musician\MusicianProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MusicianProfile>
 */
class MusicianProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MusicianProfile::class);
    }

    public function findByUsername(string $username): ?MusicianProfile
    {
        return $this->createQueryBuilder('mp')
            ->innerJoin('mp.user', 'u')
            ->where('u.username = :username')
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
