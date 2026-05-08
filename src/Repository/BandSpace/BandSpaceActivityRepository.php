<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Enum\BandSpace\BandSpaceModule;
use App\Service\BandSpace\BandSpaceActivityFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * @return BandSpaceActivity[]
     */
    public function findForBandSpace(BandSpace $bandSpace, BandSpaceActivityFilter $filter): array
    {
        return $this->buildBandSpaceQuery($bandSpace, $filter)
            ->addSelect('u')
            ->leftJoin('a.actor', 'u')
            ->orderBy('a.creationDatetime', 'DESC')
            ->setFirstResult($filter->offset)
            ->setMaxResults($filter->limit)
            ->getQuery()
            ->getResult();
    }

    public function countForBandSpace(BandSpace $bandSpace, BandSpaceActivityFilter $filter): int
    {
        return (int) $this->buildBandSpaceQuery($bandSpace, $filter)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function buildBandSpaceQuery(BandSpace $bandSpace, BandSpaceActivityFilter $filter): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace);

        if ($filter->modules !== []) {
            $qb->andWhere('a.module IN (:modules)')
                ->setParameter('modules', $filter->modules);
        }

        if ($filter->actorId !== null) {
            $qb->andWhere('IDENTITY(a.actor) = :actorId')
                ->setParameter('actorId', $filter->actorId);
        }

        if ($filter->type !== null) {
            $qb->andWhere('a.type = :type')
                ->setParameter('type', $filter->type);
        }

        if ($filter->from !== null) {
            $qb->andWhere('a.creationDatetime >= :from')
                ->setParameter('from', $filter->from);
        }

        if ($filter->to !== null) {
            $qb->andWhere('a.creationDatetime <= :to')
                ->setParameter('to', $filter->to);
        }

        return $qb;
    }
}
