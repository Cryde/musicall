<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return Task[]
     */
    public function findByBandSpace(
        BandSpace $bandSpace,
        ?string $status = null,
        ?string $categoryId = null,
        ?string $assigneeId = null,
        ?string $priority = null,
        ?bool $archived = null,
        ?string $query = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('u', 'c', 'a')
            ->leftJoin('t.createdBy', 'u')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.assignees', 'a')
            ->where('t.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.creationDatetime', 'DESC');

        if ($archived === true) {
            $qb->andWhere('t.archiveDatetime IS NOT NULL');
        } else {
            $qb->andWhere('t.archiveDatetime IS NULL');
        }

        if ($status !== null) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        if ($categoryId !== null) {
            $qb->andWhere('t.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        if ($assigneeId !== null) {
            $qb->andWhere('a.id = :assigneeId')
                ->setParameter('assigneeId', $assigneeId);
        }

        if ($priority !== null) {
            $qb->andWhere('t.priority = :priority')
                ->setParameter('priority', $priority);
        }

        $trimmedQuery = $query !== null ? trim($query) : '';
        if ($trimmedQuery !== '') {
            $qb->andWhere('LOWER(t.title) LIKE :query OR LOWER(t.description) LIKE :query')
                ->setParameter('query', '%' . mb_strtolower($trimmedQuery) . '%');
        }

        return $qb->getQuery()->getResult();
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
            'UPDATE task SET position = CASE id %s END WHERE id IN (:ids)',
            implode(' ', $caseParts)
        );

        $this->getEntityManager()->getConnection()->executeStatement(
            $sql,
            $params,
            ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );
    }

    /**
     * @param string[] $ids
     * @return Task[]
     */
    public function findByIdsAndBandSpace(array $ids, BandSpace $bandSpace): array
    {
        if (count($ids) === 0) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->andWhere('t.bandSpace = :bandSpace')
            ->setParameter('ids', $ids)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?Task
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u', 'c', 'a')
            ->leftJoin('t.createdBy', 'u')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.assignees', 'a')
            ->where('t.id = :id')
            ->andWhere('t.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

}
