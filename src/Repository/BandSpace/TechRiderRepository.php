<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\TechRider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TechRider>
 */
class TechRiderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TechRider::class);
    }

    /**
     * Live riders by default; $archivedOnly swaps to the archive view rather than
     * widening the result, matching the files trash (?archived=true) rather than
     * SetlistRepository::findByBandSpace, which includes both.
     *
     * @return TechRider[]
     */
    public function findByBandSpace(BandSpace $bandSpace, bool $archivedOnly = false): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('author')
            ->leftJoin('r.createdBy', 'author')
            ->where('r.bandSpace = :bandSpace')
            ->andWhere($archivedOnly ? 'r.archiveDatetime IS NOT NULL' : 'r.archiveDatetime IS NULL')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('r.creationDatetime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Direct id lookup does NOT filter archived riders: an archived rider still has to
     * be readable so it can be restored or duplicated into next year's.
     *
     * A non-uuid $id returns null rather than blowing up, because setParameter() with no
     * explicit type binds the plain string and it simply matches no row. That safety does
     * NOT survive a switch to find() or findOneBy(['id' => $id]), which bind through the
     * column's uuid type and throw ValueNotConvertible, surfacing as a 500 where the
     * caller wants a 404. Keep the query builder.
     * Pinned by TechRiderGetItemTest::test_get_tech_rider_with_a_non_uuid_id_is_not_found.
     */
    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?TechRider
    {
        return $this->createQueryBuilder('r')
            ->addSelect('author')
            ->leftJoin('r.createdBy', 'author')
            ->where('r.id = :id')
            ->andWhere('r.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
