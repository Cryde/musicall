<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
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
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isMember(BandSpace $bandSpace, User $user): bool
    {
        $result = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.bandSpace = :bandSpace')
            ->andWhere('m.user = :user')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * @return BandSpaceMembership[]
     */
    public function findByBandSpace(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('m.creationDatetime', 'ASC')
            ->getQuery()
            ->getResult();
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
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('role', Role::Admin)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
