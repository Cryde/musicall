<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Enum\BandSpace\BandSpaceModule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends ServiceEntityRepository<BandSpaceActivity>
 */
class BandSpaceActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceActivity::class);
    }

    /**
     * @return BandSpaceActivity[]
     */
    public function findForResource(BandSpace $bandSpace, BandSpaceModule $module, UuidInterface|string $resourceId): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('u')
            ->leftJoin('a.actor', 'u')
            ->where('a.bandSpace = :bandSpace')
            ->andWhere('a.module = :module')
            ->andWhere('a.resourceId = :resourceId')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('module', $module)
            ->setParameter('resourceId', $resourceId)
            ->orderBy('a.creationDatetime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
