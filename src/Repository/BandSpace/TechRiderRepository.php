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
        // Items are fetch-joined because every caller of this method builds the full rider,
        // so leaving them lazy just moves the query. Their file and its current version come
        // too, otherwise a document item costs two more queries each, which is the fan-out
        // this join exists to avoid. Same for patch rows, where the fan-out is per item rather
        // than per row. buildItem sorts in PHP, so no ORDER BY is needed here.
        return $this->createQueryBuilder('r')
            ->addSelect('author', 'items', 'file', 'fileVersion', 'patchRows')
            ->leftJoin('r.createdBy', 'author')
            ->leftJoin('r.items', 'items')
            ->leftJoin('items.file', 'file')
            ->leftJoin('file.currentVersion', 'fileVersion')
            ->leftJoin('items.patchRows', 'patchRows')
            ->where('r.id = :id')
            ->andWhere('r.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
