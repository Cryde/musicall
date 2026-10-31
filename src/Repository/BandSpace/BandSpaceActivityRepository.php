<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Service\BandSpace\BandSpaceActivityFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

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
    public function findForResource(BandSpace $bandSpace, BandSpaceModule $module, string $resourceId): array
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
     * The most recent activity of one type, on one resource, by one actor.
     *
     * Exists so a caller can coalesce: an autosaving editor would otherwise write a near
     * identical row every few seconds, and a feed of forty "a modifié un élément" entries
     * for one afternoon's writing is noise rather than history.
     */
    public function findLatestForResource(
        BandSpace $bandSpace,
        BandSpaceModule $module,
        string $type,
        string $resourceId,
        User $actor,
    ): ?BandSpaceActivity {
        return $this->createQueryBuilder('a')
            ->where('a.bandSpace = :bandSpace')
            ->andWhere('a.module = :module')
            ->andWhere('a.type = :type')
            ->andWhere('a.resourceId = :resourceId')
            ->andWhere('a.actor = :actor')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('module', $module)
            ->setParameter('type', $type)
            ->setParameter('resourceId', $resourceId)
            ->setParameter('actor', $actor)
            ->orderBy('a.creationDatetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Scoped by band space so an activity id belonging to another space resolves to null
     * rather than leaking that row.
     */
    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?BandSpaceActivity
    {
        return $this->createQueryBuilder('a')
            ->addSelect('u')
            ->leftJoin('a.actor', 'u')
            ->where('a.id = :id')
            ->andWhere('a.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
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

        if ($filter->from instanceof \DateTimeImmutable) {
            $qb->andWhere('a.creationDatetime >= :from')
                ->setParameter('from', $filter->from);
        }

        if ($filter->to instanceof \DateTimeImmutable) {
            $qb->andWhere('a.creationDatetime <= :to')
                ->setParameter('to', $filter->to);
        }

        return $qb;
    }

    /**
     * Per module: how many live spaces recorded an activity in the period, and how many activities.
     *
     * @return array<string, array{spaces: int, activities: int}> keyed by BandSpaceModule value
     */
    public function summarizeModulesBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<array{module: BandSpaceModule|string, spaces: int|string, activities: int|string}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('a.module AS module', 'COUNT(DISTINCT bs.id) AS spaces', 'COUNT(a.id) AS activities')
            ->innerJoin('a.bandSpace', 'bs')
            ->where('bs.deletionScheduledDatetime IS NULL')
            ->andWhere('a.creationDatetime >= :from')
            ->andWhere('a.creationDatetime < :to')
            ->groupBy('a.module')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getArrayResult();

        $summary = [];
        foreach ($rows as $row) {
            $module = $row['module'] instanceof BandSpaceModule ? $row['module']->value : $row['module'];
            $summary[$module] = ['spaces' => (int) $row['spaces'], 'activities' => (int) $row['activities']];
        }

        return $summary;
    }
}
