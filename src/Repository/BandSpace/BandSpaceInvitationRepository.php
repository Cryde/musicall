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

    public function markExpired(): int
    {
        return $this->createQueryBuilder('i')
            ->update()
            ->set('i.status', ':expiredStatus')
            ->where('i.status = :pendingStatus')
            ->andWhere('i.expirationDatetime <= :now')
            ->setParameter('expiredStatus', InvitationStatus::Expired->value)
            ->setParameter('pendingStatus', InvitationStatus::Pending->value)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
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
