<?php

declare(strict_types=1);

namespace App\Repository\Musician;

use App\Entity\Musician\MusicianProfileMedia;
use App\Entity\User;
use App\Service\Profile\Wall\WallCursor;
use App\Service\Profile\Wall\WallItemKind;
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

    /**
     * The media a member added to their musician profile, for their wall (#1316): newest first, past `$before`.
     *
     * @return list<MusicianProfileMedia>
     */
    public function findForWall(User $owner, ?WallCursor $before, int $limit): array
    {
        $query = $this->createQueryBuilder('media')
            ->innerJoin('media.musicianProfile', 'profile')
            ->where('profile.user = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('media.creationDatetime', 'DESC')
            ->addOrderBy('media.id', 'DESC')
            ->setMaxResults($limit);
        $before?->applyTo($query, WallItemKind::MusicianMedia, 'media.creationDatetime', 'media.id');

        /** @var list<MusicianProfileMedia> $media */
        $media = $query->getQuery()->getResult();

        return $media;
    }
}
