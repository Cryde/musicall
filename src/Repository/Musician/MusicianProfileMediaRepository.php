<?php

declare(strict_types=1);

namespace App\Repository\Musician;

use App\Entity\Musician\MusicianProfileMedia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MusicianProfileMedia>
 */
class MusicianProfileMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MusicianProfileMedia::class);
    }

    public function countByMusicianProfile(string $musicianProfileId): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.musicianProfile = :profileId')
            ->setParameter('profileId', $musicianProfileId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getNextPosition(string $musicianProfileId): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('MAX(m.position)')
            ->where('m.musicianProfile = :profileId')
            ->setParameter('profileId', $musicianProfileId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int) $result + 1 : 0;
    }
}
