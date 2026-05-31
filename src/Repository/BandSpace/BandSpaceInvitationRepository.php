<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceInvitation;
use App\Enum\BandSpace\InvitationStatus;
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
