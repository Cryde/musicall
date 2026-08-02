<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\TechRiderItem;
use App\Entity\BandSpace\TechRiderPatchRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TechRiderPatchRow>
 */
class TechRiderPatchRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TechRiderPatchRow::class);
    }

    /**
     * @return TechRiderPatchRow[] inputs then outputs, each in position order
     */
    public function findByItem(TechRiderItem $item): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.item = :item')
            ->setParameter('item', $item)
            ->orderBy('r.direction', 'ASC')
            ->addOrderBy('r.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Bulk DQL rather than removing each row through the ORM: a save replaces the whole grid,
     * so up to 128 rows would otherwise be hydrated only to be thrown away.
     *
     * Safe here because a row owns nothing. It has no uploaded file, so no VichUploader
     * lifecycle event is skipped, and nothing cascades from it. The one ORM consequence that
     * does apply is the identity map: rows loaded earlier in the request survive this call, so
     * the caller must not read $item->patchRows afterwards without resetting it. The replace
     * procedure clears and refills that collection for exactly that reason.
     */
    public function deleteByItem(TechRiderItem $item): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\BandSpace\TechRiderPatchRow r WHERE r.item = :item')
            ->setParameter('item', $item)
            ->execute();
    }
}
