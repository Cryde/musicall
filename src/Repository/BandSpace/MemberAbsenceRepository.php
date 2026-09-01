<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\MemberAbsence;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberAbsence>
 */
class MemberAbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MemberAbsence::class);
    }

    /**
     * Every absence in the band whose range intersects [$from, $to]. Both bounds are inclusive, so an
     * absence that merely touches the edge of the window is in it.
     *
     * @return MemberAbsence[]
     */
    public function findOverlappingForBand(BandSpace $bandSpace, DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->withMember()
            ->where('m.bandSpace = :bandSpace')
            ->andWhere('a.startDate <= :to')
            ->andWhere('a.endDate >= :from')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.startDate', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Every reader needs the member and their user on each row, because the display name and the
     * avatar are always rendered. One proxy load per absence would undo the point of the query.
     */
    private function withMember(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->addSelect('m', 'u')
            ->innerJoin('a.member', 'm')
            ->innerJoin('m.user', 'u');
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?MemberAbsence
    {
        return $this->withMember()
            ->where('a.id = :id')
            ->andWhere('m.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
