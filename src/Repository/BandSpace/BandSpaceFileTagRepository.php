<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFileTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceFileTag>
 */
class BandSpaceFileTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceFileTag::class);
    }

    /**
     * @return BandSpaceFileTag[]
     */
    public function findByBandSpace(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?BandSpaceFileTag
    {
        return $this->createQueryBuilder('t')
            ->where('t.id = :id')
            ->andWhere('t.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function nameExists(BandSpace $bandSpace, string $name, ?BandSpaceFileTag $exclude = null): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('1')
            ->where('t.bandSpace = :bandSpace')
            ->andWhere('LOWER(t.name) = LOWER(:name)')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('name', $name)
            ->setMaxResults(1);

        if ($exclude instanceof \App\Entity\BandSpace\BandSpaceFileTag) {
            $qb->andWhere('t.id <> :excludeId')
                ->setParameter('excludeId', (string) $exclude->id);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }
}
