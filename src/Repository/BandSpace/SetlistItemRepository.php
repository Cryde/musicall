<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SetlistItem>
 */
class SetlistItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SetlistItem::class);
    }

    public function findOneByIdAndSetlist(string $id, Setlist $setlist): ?SetlistItem
    {
        return $this->createQueryBuilder('i')
            ->where('i.id = :id')
            ->andWhere('i.setlist = :setlist')
            ->setParameter('id', $id)
            ->setParameter('setlist', $setlist)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string[] $ids
     * @return SetlistItem[]
     */
    public function findByIdsAndSetlist(array $ids, Setlist $setlist): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('i')
            ->where('i.id IN (:ids)')
            ->andWhere('i.setlist = :setlist')
            ->setParameter('ids', $ids)
            ->setParameter('setlist', $setlist)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int, array{id: string, position: int}> $positions
     */
    public function bulkUpdatePositions(array $positions): void
    {
        if (count($positions) === 0) {
            return;
        }

        $caseParts = [];
        $params = [];
        foreach ($positions as $index => $item) {
            $caseParts[] = sprintf('WHEN :id%d THEN :pos%d', $index, $index);
            $params['id' . $index] = $item['id'];
            $params['pos' . $index] = $item['position'];
        }

        $params['ids'] = array_column($positions, 'id');

        $sql = sprintf(
            'UPDATE band_space_setlist_item SET position = CASE id %s END WHERE id IN (:ids)',
            implode(' ', $caseParts)
        );

        $this->getEntityManager()->getConnection()->executeStatement(
            $sql,
            $params,
            ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );
    }
}
