<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
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
