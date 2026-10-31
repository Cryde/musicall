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
     * pointless work, and almost every child table cascades from band_space at database level anyway.
     *
     * Almost: message_thread is the exception. Its cascade cannot walk the cycle the message tables
     * form, so a space's channels have to be deleted first, through
     * MessageThreadRepository::deleteByBandSpace(). app:band-space:purge does that in the same
     * transaction as this call.
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

    public function countLive(): int
    {
        return (int) $this->createQueryBuilder('bs')
            ->select('COUNT(bs.id)')
            ->where('bs.deletionScheduledDatetime IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPendingDeletion(): int
    {
        return (int) $this->createQueryBuilder('bs')
            ->select('COUNT(bs.id)')
            ->where('bs.deletionScheduledDatetime IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Live spaces with a module activity or a band chat message since the given instant. */
    public function countLiveActiveSince(\DateTimeImmutable $since): int
    {
        return (int) $this->getEntityManager()
            ->createQuery(
                'SELECT COUNT(bs.id) FROM App\Entity\BandSpace\BandSpace bs
                 WHERE bs.deletionScheduledDatetime IS NULL
                   AND (
                     EXISTS (SELECT a.id FROM App\Entity\BandSpace\BandSpaceActivity a WHERE a.bandSpace = bs AND a.creationDatetime >= :since)
                     OR EXISTS (SELECT m.id FROM App\Entity\Message\Message m JOIN m.thread t WHERE t.bandSpace = bs AND m.creationDatetime >= :since)
                   )'
            )
            ->setParameter('since', $since)
            ->getSingleScalarResult();
    }

    /**
     * The active member count of every live space, one entry per space.
     *
     * @return list<int>
     */
    public function findLiveActiveMemberCounts(): array
    {
        $rows = $this->createQueryBuilder('bs')
            ->select('COUNT(m.id) AS members')
            ->leftJoin('bs.memberships', 'm', 'WITH', 'm.status = :active')
            ->where('bs.deletionScheduledDatetime IS NULL')
            ->groupBy('bs.id')
            ->setParameter('active', MembershipStatus::Active)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_map(intval(...), $rows));
    }

    /**
     * Spaces created per day, the ones pending deletion included: it is a history of creations.
     *
     * @return array<int, array{date_label: string, count: int}>
     */
    public function countCreationsByDate(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT DATE(creation_datetime) AS date_label, COUNT(id) AS count
             FROM band_space
             WHERE creation_datetime >= :from AND creation_datetime < :to
             GROUP BY DATE(creation_datetime)
             ORDER BY date_label ASC',
            ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
        );

        return array_map(
            fn (array $row): array => ['date_label' => (string) $row['date_label'], 'count' => (int) $row['count']],
            $rows,
        );
    }
}
