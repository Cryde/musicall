<?php

declare(strict_types=1);

namespace App\Repository\Teacher;

use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfileMedia;
use App\Entity\User;
use App\Service\Profile\Wall\WallCursor;
use App\Service\Profile\Wall\WallItemKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeacherProfileMedia>
 */
class TeacherProfileMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeacherProfileMedia::class);
    }

    public function getNextPosition(TeacherProfile $teacherProfile): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('MAX(m.position)')
            ->where('m.teacherProfile = :profile')
            ->setParameter('profile', $teacherProfile)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int) $result + 1 : 0;
    }

    public function countByTeacherProfile(string $teacherProfileId): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.teacherProfile = :profileId')
            ->setParameter('profileId', $teacherProfileId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The media a member added to their teacher profile, for their wall (#1316): newest first, past `$before`.
     *
     * @return list<TeacherProfileMedia>
     */
    public function findForWall(User $owner, ?WallCursor $before, int $limit): array
    {
        $query = $this->createQueryBuilder('media')
            ->innerJoin('media.teacherProfile', 'profile')
            ->where('profile.user = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('media.creationDatetime', 'DESC')
            ->addOrderBy('media.id', 'DESC')
            ->setMaxResults($limit);
        $before?->applyTo($query, WallItemKind::TeacherMedia, 'media.creationDatetime', 'media.id');

        /** @var list<TeacherProfileMedia> $media */
        $media = $query->getQuery()->getResult();

        return $media;
    }
}
