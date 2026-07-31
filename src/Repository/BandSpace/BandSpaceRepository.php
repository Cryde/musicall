<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpace>
 */
class BandSpaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpace::class);
    }

    /**
     * @return BandSpace[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('bs')
            ->innerJoin('bs.memberships', 'm')
            ->addSelect('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.user = :user')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', MembershipStatus::Active)
            ->orderBy('bs.creationDatetime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Spaces whose grace period has elapsed, for app:band-space:purge.
     *
     * @return BandSpace[]
     */
    public function findScheduledForDeletion(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('bs')
            ->where('bs.deletionScheduledDatetime IS NOT NULL')
            ->andWhere('bs.deletionScheduledDatetime <= :now')
            ->setParameter('now', $now)
            ->orderBy('bs.deletionScheduledDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Hard-deletes a space in one statement, for app:band-space:purge. Deliberately a bulk DQL delete
     * rather than an ORM remove(): hydrating a whole space and its children just to delete them would be
     * pointless work, and every child table cascades from band_space at database level anyway.
     *
     * Two consequences the caller has to know about. A bulk delete never fires lifecycle events, so
     * VichUploader does not run and the stored objects must be removed separately - which is exactly why
     * app:band-space:purge deletes the storage prefix first. And the entities stay in Doctrine's identity
     * map, so anything that re-reads them in the same process sees rows that no longer exist.
     */
    public function deleteById(string $id): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\BandSpace\BandSpace bs WHERE bs.id = :id')
            ->setParameter('id', $id)
            ->execute();
    }

    public function countAdminByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('bs')
            ->select('COUNT(bs.id)')
            ->innerJoin('bs.memberships', 'm')
            ->where('m.user = :user')
            ->andWhere('m.role = :role')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('role', Role::Admin)
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByIdWithMemberships(string $id): ?BandSpace
    {
        return $this->createQueryBuilder('band_space')
            ->leftJoin('band_space.memberships', 'memberships')
            ->leftJoin('memberships.user', 'user')
            ->addSelect('memberships', 'user')
            ->where('band_space.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
