<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\TechRider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

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
     * The id is validated before it reaches the query because the column is a uuid and
     * Doctrine throws ValueNotConvertible on a non-uuid value, which surfaces as a 500
     * where the caller wants a 404.
     */
    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?TechRider
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

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
