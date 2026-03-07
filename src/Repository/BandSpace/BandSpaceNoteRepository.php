<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceNote>
 */
class BandSpaceNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceNote::class);
    }

    /**
     * @return BandSpaceNote[]
     */
    public function findByBandSpace(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('n.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?BandSpaceNote
    {
        return $this->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('n.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getNextPosition(BandSpace $bandSpace, ?BandSpaceNote $parent): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('MAX(n.position)')
            ->where('n.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace);

        if ($parent !== null) {
            $qb->andWhere('n.parent = :parent')
                ->setParameter('parent', $parent);
        } else {
            $qb->andWhere('n.parent IS NULL');
        }

        $maxPosition = $qb->getQuery()->getSingleScalarResult();

        return $maxPosition !== null ? ((int) $maxPosition) + 1 : 0;
    }
}
