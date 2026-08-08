<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceMembership>
 */
class BandSpaceMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceMembership::class);
    }

    public function findMembership(BandSpace $bandSpace, User $user): ?BandSpaceMembership
    {
        return $this->createQueryBuilder('m')
            ->where('m.bandSpace = :bandSpace')
            ->andWhere('m.user = :user')
            ->andWhere('m.status = :status')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('user', $user)
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isMember(BandSpace $bandSpace, User $user): bool
    {
        $result = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.bandSpace = :bandSpace')
            ->andWhere('m.user = :user')
            ->andWhere('m.status = :status')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('user', $user)
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * @return BandSpaceMembership[]
     */
    public function findByBandSpace(BandSpace $bandSpace, bool $includeInactive = false): array
    {
        // Instruments come along because every caller that lists members now prints them, so
        // leaving them lazy would cost one query per member on a roster.
        $qb = $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->leftJoin('m.instruments', 'i')
            ->addSelect('i')
            ->where('m.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('m.creationDatetime', 'ASC');

        if (!$includeInactive) {
            $qb->andWhere('m.status = :status')
                ->setParameter('status', MembershipStatus::Active);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?BandSpaceMembership
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.id = :id')
            ->andWhere('m.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countAdmins(BandSpace $bandSpace): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.bandSpace = :bandSpace')
            ->andWhere('m.role = :role')
            ->andWhere('m.status = :status')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('role', Role::Admin)
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveMembers(BandSpace $bandSpace): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.bandSpace = :bandSpace')
            ->andWhere('m.status = :status')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Every space the user still belongs to, for the account-deletion sweep. The space comes along
     * because the caller reads and writes it for each membership.
     *
     * @return BandSpaceMembership[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.bandSpace', 'bs')
            ->addSelect('bs')
            ->where('m.user = :user')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', MembershipStatus::Active)
            ->orderBy('m.creationDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * The active member who has been in the space the longest, ignoring one of them.
     *
     * Used to pick the successor when the last admin disappears. Ordered by join date and then by id so
     * two members who joined within the same second still resolve to one and the same successor: the
     * column is a DATETIME, and a batch of invitations accepted at registration writes identical values.
     */
    public function findLongestStandingActiveMemberExcept(BandSpace $bandSpace, BandSpaceMembership $excluded): ?BandSpaceMembership
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.bandSpace = :bandSpace')
            ->andWhere('m.status = :status')
            ->andWhere('m.id != :excludedId')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('status', MembershipStatus::Active)
            ->setParameter('excludedId', (string) $excluded->id)
            ->orderBy('m.creationDatetime', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Which of these band spaces the user is still an active member of.
     *
     * One query for a whole notification page: read-time enrichment refreshes band space data as it
     * stands right now, so it may only do that for the spaces the reader can still open.
     *
     * @param string[] $bandSpaceIds
     * @return array<string, true> band space id => true, for an isset() lookup at the call site
     */
    public function findActiveBandSpaceIdsForUser(User $user, array $bandSpaceIds): array
    {
        if ($bandSpaceIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.bandSpace) AS band_space_id')
            ->where('m.user = :user')
            ->andWhere('m.bandSpace IN (:bandSpaceIds)')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('bandSpaceIds', $bandSpaceIds)
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->getArrayResult();

        $activeIds = [];
        foreach ($rows as $row) {
            $activeIds[(string) $row['band_space_id']] = true;
        }

        return $activeIds;
    }

    public function findMembershipIncludingInactive(BandSpace $bandSpace, User $user): ?BandSpaceMembership
    {
        return $this->createQueryBuilder('m')
            ->where('m.bandSpace = :bandSpace')
            ->andWhere('m.user = :user')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
