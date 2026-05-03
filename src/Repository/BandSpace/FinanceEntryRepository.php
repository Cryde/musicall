<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\FinanceEntry;
use App\Enum\BandSpace\FinanceEntryStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FinanceEntry>
 */
class FinanceEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinanceEntry::class);
    }

    private static function effectiveAmountSql(string $alias = 'e'): string
    {
        return "COALESCE({$alias}.amount, ROUND(({$alias}.amount_min + {$alias}.amount_max) / 2), 0)";
    }

    /**
     * @return array{string, array<string, string>}
     */
    private static function buildDateFilter(BandSpace $bandSpace, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): array
    {
        $filter = '';
        $params = ['bandSpaceId' => (string) $bandSpace->id];
        if ($from !== null) {
            $filter .= ' AND e.date >= :from';
            $params['from'] = $from->format('Y-m-d');
        }
        if ($to !== null) {
            $filter .= ' AND e.date < :to';
            $params['to'] = $to->format('Y-m-d');
        }

        return [$filter, $params];
    }

    /**
     * @return FinanceEntry[]
     */
    public function findByBandSpace(BandSpace $bandSpace, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.category', 'c')->addSelect('c')
            ->join('c.bandSpace', 'bs')->addSelect('bs')
            ->leftJoin('e.member', 'm')->addSelect('m')
            ->leftJoin('m.user', 'u')->addSelect('u')
            ->where('c.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('e.date', 'DESC');

        if ($from !== null) {
            $qb->andWhere('e.date >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('e.date < :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?FinanceEntry
    {
        return $this->createQueryBuilder('e')
            ->join('e.category', 'c')->addSelect('c')
            ->join('c.bandSpace', 'bs')->addSelect('bs')
            ->leftJoin('e.member', 'm')->addSelect('m')
            ->leftJoin('m.user', 'u')->addSelect('u')
            ->where('e.id = :id')
            ->andWhere('c.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array{min_date: ?string, max_date: ?string}
     */
    public function getDateBoundaries(BandSpace $bandSpace): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT MIN(e.date) AS min_date, MAX(e.date) AS max_date
            FROM finance_entry e
            JOIN finance_category c ON e.category_id = c.id
            WHERE c.band_space_id = :bandSpaceId
            SQL;

        $result = $conn->executeQuery($sql, ['bandSpaceId' => (string) $bandSpace->id])->fetchAssociative();
        \assert($result !== false);

        return [
            'min_date' => $result['min_date'] ? (new \DateTimeImmutable($result['min_date']))->format(\DateTimeInterface::ATOM) : null,
            'max_date' => $result['max_date'] ? (new \DateTimeImmutable($result['max_date']))->format(\DateTimeInterface::ATOM) : null,
        ];
    }

    /**
     * @return array{total_income: int, total_expense: int, total_planned: int, total_committed: int, total_paid: int, total_personal: int, has_estimates: bool}
     */
    public function getSummaryByBandSpace(BandSpace $bandSpace, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $effectiveAmount = self::effectiveAmountSql();
        [$dateFilter, $params] = self::buildDateFilter($bandSpace, $from, $to);

        $sql = <<<SQL
            SELECT
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.type = 'income' AND e.status = 'paid' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.type = 'expense' AND e.status = 'paid' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_expense,
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.status = 'planned' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_planned,
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.status = 'committed' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_committed,
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.status = 'paid' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN e.scope = 'personal' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_personal,
                MAX(CASE WHEN e.amount IS NULL AND e.amount_min IS NOT NULL THEN 1 ELSE 0 END) AS has_estimates
            FROM finance_entry e
            JOIN finance_category c ON e.category_id = c.id
            WHERE c.band_space_id = :bandSpaceId{$dateFilter}
            SQL;

        $result = $conn->executeQuery($sql, $params)->fetchAssociative();
        \assert($result !== false);

        return [
            'total_income' => (int) $result['total_income'],
            'total_expense' => (int) $result['total_expense'],
            'total_planned' => (int) $result['total_planned'],
            'total_committed' => (int) $result['total_committed'],
            'total_paid' => (int) $result['total_paid'],
            'total_personal' => (int) $result['total_personal'],
            'has_estimates' => (bool) $result['has_estimates'],
        ];
    }

    /**
     * @return array<int, array{pole_id: string, pole_name: string, paid: int, committed: int, planned: int}>
     */
    public function getSummaryByCategory(BandSpace $bandSpace, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $effectiveAmount = self::effectiveAmountSql();
        [$dateFilter, $params] = self::buildDateFilter($bandSpace, $from, $to);

        $sql = <<<SQL
            SELECT
                pole.id AS pole_id,
                pole.name AS pole_name,
                COALESCE(SUM(CASE WHEN e.status = 'paid' THEN {$effectiveAmount} ELSE 0 END), 0) AS paid,
                COALESCE(SUM(CASE WHEN e.status = 'committed' THEN {$effectiveAmount} ELSE 0 END), 0) AS committed,
                COALESCE(SUM(CASE WHEN e.status = 'planned' THEN {$effectiveAmount} ELSE 0 END), 0) AS planned
            FROM finance_category pole
            LEFT JOIN finance_category child ON child.parent_id = pole.id
            LEFT JOIN finance_entry e ON (e.category_id = pole.id OR e.category_id = child.id){$dateFilter}
            WHERE pole.band_space_id = :bandSpaceId AND pole.parent_id IS NULL
            GROUP BY pole.id, pole.name
            ORDER BY pole.position ASC
            SQL;

        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        return array_map(fn (array $row) => [
            'pole_id' => $row['pole_id'],
            'pole_name' => $row['pole_name'],
            'paid' => (int) $row['paid'],
            'committed' => (int) $row['committed'],
            'planned' => (int) $row['planned'],
        ], $rows);
    }

    /**
     * @return FinanceEntry[]
     */
    public function findUpcomingForBand(BandSpace $bandSpace, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('c')
            ->join('e.category', 'c')
            ->where('c.bandSpace = :bandSpace')
            ->andWhere('e.date >= :from')
            ->andWhere('e.date <= :to')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return FinanceEntry[]
     */
    public function getUpcomingByBandSpace(BandSpace $bandSpace, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, int $limit = 5): array
    {
        $now = new \DateTimeImmutable();
        $effectiveFrom = $from !== null && $from > $now ? $from : $now;

        $qb = $this->createQueryBuilder('e')
            ->join('e.category', 'c')
            ->where('c.bandSpace = :bandSpace')
            ->andWhere('e.date >= :from')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('from', $effectiveFrom)
            ->setParameter('statuses', [FinanceEntryStatus::Planned->value, FinanceEntryStatus::Committed->value])
            ->orderBy('e.date', 'ASC')
            ->setMaxResults($limit);

        if ($to !== null) {
            $qb->andWhere('e.date < :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, array{member_id: string, username: string, total: int}>
     */
    public function getMemberContributions(BandSpace $bandSpace, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        [$dateFilter, $params] = self::buildDateFilter($bandSpace, $from, $to);

        $sql = <<<SQL
            SELECT
                m.id AS member_id,
                u.username,
                COALESCE(SUM(s.amount), 0) AS total
            FROM finance_entry_split s
            JOIN finance_entry e ON s.entry_id = e.id
            JOIN finance_category c ON e.category_id = c.id
            JOIN band_space_membership m ON s.member_id = m.id
            JOIN fos_user u ON m.user_id = u.id
            WHERE c.band_space_id = :bandSpaceId{$dateFilter}
            GROUP BY m.id, u.username
            ORDER BY total DESC
            SQL;

        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        return array_map(fn (array $row) => [
            'member_id' => $row['member_id'],
            'username' => $row['username'],
            'total' => (int) $row['total'],
        ], $rows);
    }
}
