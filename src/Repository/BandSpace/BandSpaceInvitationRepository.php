<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceInvitation;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\MembershipStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceInvitation>
 */
class BandSpaceInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceInvitation::class);
    }

    /**
     * @return BandSpaceInvitation[]
     */
    public function findPendingByBandSpace(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.bandSpace = :bandSpace')
            ->andWhere('i.status = :status')
            ->andWhere('i.expirationDatetime > :now')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('status', InvitationStatus::Pending)
            ->setParameter('now', new \DateTime())
            ->orderBy('i.creationDatetime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingByToken(string $token): ?BandSpaceInvitation
    {
        return $this->createQueryBuilder('i')
            ->where('i.token = :token')
            ->andWhere('i.status = :status')
            ->andWhere('i.expirationDatetime > :now')
            ->setParameter('token', $token)
            ->setParameter('status', InvitationStatus::Pending)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Bulk lookup by token (any status) - used to resolve live invitation status
     * for the notification feed in one query.
     *
     * @param string[] $tokens
     * @return BandSpaceInvitation[]
     */
    public function findByTokens(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        return $this->createQueryBuilder('i')
            ->where('i.token IN (:tokens)')
            ->setParameter('tokens', $tokens)
            ->getQuery()
            ->getResult();
    }

    public function findPendingByEmailAndBandSpace(string $email, BandSpace $bandSpace): ?BandSpaceInvitation
    {
        return $this->createQueryBuilder('i')
            ->where('i.email = :email')
            ->andWhere('i.bandSpace = :bandSpace')
            ->andWhere('i.status = :status')
            ->andWhere('i.expirationDatetime > :now')
            ->setParameter('email', $email)
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('status', InvitationStatus::Pending)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return BandSpaceInvitation[]
     */
    public function findPendingByEmail(string $email): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.email = :email')
            ->andWhere('i.status = :status')
            ->andWhere('i.expirationDatetime > :now')
            ->setParameter('email', $email)
            ->setParameter('status', InvitationStatus::Pending)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Same recipient rule as accept; the email column's collation is case insensitive, like accept's comparison.
     *
     * @return list<BandSpaceInvitation>
     */
    public function findAcceptableBy(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->addSelect('bandSpace', 'inviter')
            ->innerJoin('i.bandSpace', 'bandSpace')
            ->innerJoin('i.invitedBy', 'inviter')
            ->where('i.existingUser = :user OR (i.existingUser IS NULL AND i.email = :email)')
            ->andWhere('i.status = :status')
            ->andWhere('i.expirationDatetime > :now')
            ->andWhere('bandSpace.deletionScheduledDatetime IS NULL')
            ->andWhere('NOT EXISTS (SELECT 1 FROM ' . BandSpaceMembership::class . ' membership'
                . ' WHERE membership.bandSpace = bandSpace AND membership.user = :user AND membership.status = :active)')
            ->setParameter('user', $user)
            ->setParameter('email', $user->email)
            ->setParameter('status', InvitationStatus::Pending)
            ->setParameter('now', new \DateTime())
            ->setParameter('active', MembershipStatus::Active)
            ->orderBy('i.creationDatetime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return BandSpaceInvitation[]
     */
    public function findExpiredPending(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.status = :pendingStatus')
            ->andWhere('i.expirationDatetime <= :now')
            ->setParameter('pendingStatus', InvitationStatus::Pending)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?BandSpaceInvitation
    {
        return $this->createQueryBuilder('i')
            ->where('i.id = :id')
            ->andWhere('i.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
