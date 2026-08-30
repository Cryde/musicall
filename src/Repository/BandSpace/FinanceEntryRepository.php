<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceCategory;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * What an entry contributes to a total: its exact amount, else the middle of its estimate.
     *
     * The two lone bounds are the safety net for rows written before a fourchette had to carry both of
     * them. `amount_min + amount_max` is NULL as soon as one side is, so those rows used to fall all the
     * way through to 0 and vanish from every total. Falling back to the bound that is actually there is
     * wrong by at most half a bracket, where 0 was wrong by the whole entry.
     */
    private function effectiveAmountSql(string $alias = 'e'): string
    {
        return "COALESCE({$alias}.amount, ROUND(({$alias}.amount_min + {$alias}.amount_max) / 2), {$alias}.amount_min, {$alias}.amount_max, 0)";
    }

    /**
     * @return array{string, array<string, string>}
     */
    private function buildDateFilter(BandSpace $bandSpace, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): array
    {
        $filter = '';
        $params = ['bandSpaceId' => (string) $bandSpace->id];
        if ($from instanceof \DateTimeImmutable) {
            $filter .= ' AND e.date >= :from';
            $params['from'] = $from->format('Y-m-d');
        }
        if ($to instanceof \DateTimeImmutable) {
            $filter .= ' AND e.date < :to';
            $params['to'] = $to->format('Y-m-d');
        }

        return [$filter, $params];
    }

    /**
     * What a given member is allowed to read: everything the band owns, plus their own personal
     * entries and nobody else's.
     *
     * The rule lives here rather than in each caller because it is a read rule for the whole module,
     * and a query that forgets it does not fail, it quietly hands one member another member's private
     * spending. The `$viewer` argument is required for the same reason: adding a read path that
     * should have been filtered then does not compile.
     *
     * A personal entry with no member is deliberately visible to nobody. It should not exist (the
     * create and update paths both refuse one, and memberships are closed rather than deleted so the
     * SET NULL never fires), so if one ever appears it is a data fault, and hiding somebody's private
     * row is the safer failure.
     */
    private function applyVisibleTo(QueryBuilder $qb, BandSpaceMembership $viewer): QueryBuilder
    {
        return $qb
            ->andWhere('e.scope = :bandScope OR e.member = :viewer')
            ->setParameter('bandScope', FinanceEntryScope::Band)
            ->setParameter('viewer', $viewer);
    }

    /** The same rule for the raw SQL aggregates, which cannot go through the query builder. */
    private function visibleToSql(string $alias = 'e'): string
    {
        return "({$alias}.scope = 'band' OR {$alias}.member_id = :viewerId)";
    }

    /**
     * @return FinanceEntry[]
     */
    public function findByBandSpace(
        BandSpace $bandSpace,
        BandSpaceMembership $viewer,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->join('e.category', 'c')->addSelect('c')
            ->join('c.bandSpace', 'bs')->addSelect('bs')
            ->leftJoin('e.member', 'm')->addSelect('m')
            ->leftJoin('m.user', 'u')->addSelect('u')
            ->where('c.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('e.date', 'DESC');

        $this->applyVisibleTo($qb, $viewer);

        if ($from instanceof \DateTimeImmutable) {
            $qb->andWhere('e.date >= :from')->setParameter('from', $from);
        }
        if ($to instanceof \DateTimeImmutable) {
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
    public function getDateBoundaries(BandSpace $bandSpace, BandSpaceMembership $viewer): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $visible = $this->visibleToSql();

        $sql = <<<SQL
            SELECT MIN(e.date) AS min_date, MAX(e.date) AS max_date
            FROM finance_entry e
            JOIN finance_category c ON e.category_id = c.id
            WHERE c.band_space_id = :bandSpaceId AND {$visible}
            SQL;

        $result = $conn->executeQuery($sql, [
            'bandSpaceId' => (string) $bandSpace->id,
            'viewerId' => (string) $viewer->id,
        ])->fetchAssociative();
        \assert($result !== false);

        return [
            'min_date' => $result['min_date'] ? (new \DateTimeImmutable($result['min_date']))->format(\DateTimeInterface::ATOM) : null,
            'max_date' => $result['max_date'] ? (new \DateTimeImmutable($result['max_date']))->format(\DateTimeInterface::ATOM) : null,
        ];
    }

    /**
     * @return array{total_income: int, total_expense: int, total_income_all: int, total_expense_all: int, total_planned: int, total_committed: int, total_paid: int, total_personal: int, has_estimates: bool}
     */
    public function getSummaryByBandSpace(
        BandSpace $bandSpace,
        BandSpaceMembership $viewer,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $conn = $this->getEntityManager()->getConnection();
        $effectiveAmount = $this->effectiveAmountSql();
        [$dateFilter, $params] = $this->buildDateFilter($bandSpace, $from, $to);
        $params['viewerId'] = (string) $viewer->id;
        $visible = $this->visibleToSql();

        $sql = <<<SQL
            SELECT
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.type = 'income' AND e.status = 'paid' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.type = 'expense' AND e.status = 'paid' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_expense,
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.type = 'income' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_income_all,
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.type = 'expense' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_expense_all,
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.status = 'planned' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_planned,
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.status = 'committed' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_committed,
                COALESCE(SUM(CASE WHEN e.scope = 'band' AND e.status = 'paid' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN e.scope = 'personal' THEN {$effectiveAmount} ELSE 0 END), 0) AS total_personal,
                MAX(CASE WHEN e.amount IS NULL AND (e.amount_min IS NOT NULL OR e.amount_max IS NOT NULL) THEN 1 ELSE 0 END) AS has_estimates
            FROM finance_entry e
            JOIN finance_category c ON e.category_id = c.id
            WHERE c.band_space_id = :bandSpaceId AND {$visible}{$dateFilter}
            SQL;

        $result = $conn->executeQuery($sql, $params)->fetchAssociative();
        \assert($result !== false);

        return [
            'total_income' => (int) $result['total_income'],
            'total_expense' => (int) $result['total_expense'],
            'total_income_all' => (int) $result['total_income_all'],
            'total_expense_all' => (int) $result['total_expense_all'],
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
    public function getSummaryByCategory(
        BandSpace $bandSpace,
        BandSpaceMembership $viewer,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $conn = $this->getEntityManager()->getConnection();
        $effectiveAmount = $this->effectiveAmountSql();
        [$dateFilter, $params] = $this->buildDateFilter($bandSpace, $from, $to);
        $params['viewerId'] = (string) $viewer->id;
        $visible = $this->visibleToSql();

        $sql = <<<SQL
            SELECT
                pole.id AS pole_id,
                pole.name AS pole_name,
                COALESCE(SUM(CASE WHEN e.status = 'paid' THEN {$effectiveAmount} ELSE 0 END), 0) AS paid,
                COALESCE(SUM(CASE WHEN e.status = 'committed' THEN {$effectiveAmount} ELSE 0 END), 0) AS committed,
                COALESCE(SUM(CASE WHEN e.status = 'planned' THEN {$effectiveAmount} ELSE 0 END), 0) AS planned
            FROM finance_category pole
            LEFT JOIN finance_category child ON child.parent_id = pole.id
            LEFT JOIN finance_entry e ON (e.category_id = pole.id OR e.category_id = child.id) AND {$visible}{$dateFilter}
            WHERE pole.band_space_id = :bandSpaceId AND pole.parent_id IS NULL
            GROUP BY pole.id, pole.name
            ORDER BY pole.position ASC
            SQL;

        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        return array_map(fn (array $row): array => [
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
    public function findUpcomingForBand(
        BandSpace $bandSpace,
        BandSpaceMembership $viewer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->addSelect('c')
            ->join('e.category', 'c')
            ->where('c.bandSpace = :bandSpace')
            ->andWhere('e.date >= :from')
            ->andWhere('e.date <= :to')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('e.date', 'ASC');

        return $this->applyVisibleTo($qb, $viewer)->getQuery()->getResult();
    }

    /**
     * @return FinanceEntry[]
     */
    public function getUpcomingByBandSpace(
        BandSpace $bandSpace,
        BandSpaceMembership $viewer,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        int $limit = 5,
    ): array {
        $now = new \DateTimeImmutable();
        $effectiveFrom = $from instanceof \DateTimeImmutable && $from > $now ? $from : $now;

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

        $this->applyVisibleTo($qb, $viewer);

        if ($to instanceof \DateTimeImmutable) {
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
        [$dateFilter, $params] = $this->buildDateFilter($bandSpace, $from, $to);

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

        return array_map(fn (array $row): array => [
            'member_id' => $row['member_id'],
            'username' => $row['username'],
            'total' => (int) $row['total'],
        ], $rows);
    }

    /**
     * Id => label of the planned entries deletePlannedByRecurrence() would drop, same predicate.
     *
     * A projection, not a hydration: the only caller detaches the files hanging on those entries before
     * the bulk delete removes them, and for that it needs an id and something to name the source with.
     *
     * @return array<string, string>
     */
    public function findPlannedLabelsByRecurrence(FinanceRecurrence $recurrence, ?\DateTimeInterface $after = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.id AS id', 'e.label AS label')
            ->where('e.recurrence = :recurrence')
            ->andWhere('e.status = :status')
            ->setParameter('recurrence', $recurrence)
            ->setParameter('status', FinanceEntryStatus::Planned);

        if ($after instanceof \DateTimeInterface) {
            $qb->andWhere('e.date > :after')->setParameter('after', $after);
        }

        $rows = $qb->getQuery()->getArrayResult();

        $labels = [];
        foreach ($rows as $row) {
            $labels[(string) $row['id']] = (string) $row['label'];
        }

        return $labels;
    }

    /**
     * Id => label of every entry filed under a category, whatever its status.
     *
     * `finance_entry.category_id` is `ON DELETE CASCADE` and `FinanceCategory` declares no inverse
     * collection, so deleting a category takes its entries with it in the database without Doctrine
     * ever loading them. The caller needs this list to detach the files hanging on those entries
     * first, since nothing afterwards can name a source that no longer exists.
     *
     * @return array<string, string>
     */
    public function findLabelsByCategory(FinanceCategory $category): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.id AS id', 'e.label AS label')
            ->where('e.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getArrayResult();

        $labels = [];
        foreach ($rows as $row) {
            $labels[(string) $row['id']] = (string) $row['label'];
        }

        return $labels;
    }

    /**
     * How many entries each of these categories holds, keyed by category id. A category with none is
     * absent from the map rather than present at zero, so callers default it themselves.
     *
     * One query for the whole tree: the interface names the number in the delete confirmation of every
     * category it draws, and that must not cost one round trip per pole.
     *
     * @param FinanceCategory[] $categories
     * @return array<string, int>
     */
    public function countByCategories(array $categories): array
    {
        if ($categories === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.category) AS category_id, COUNT(e.id) AS cnt')
            ->where('e.category IN (:categories)')
            ->setParameter('categories', $categories)
            ->groupBy('e.category')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['category_id']] = (int) $row['cnt'];
        }

        return $counts;
    }

    public function countByCategory(FinanceCategory $category): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * How many Paid entries a category holds. Deleting the category cascades them in the database, which
     * is the one route around FinanceEntryDeleteProcessor's refusal to delete a paid entry, so the
     * delete asks this first.
     */
    public function countPaidByCategory(FinanceCategory $category): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.category = :category')
            ->andWhere('e.status = :status')
            ->setParameter('category', $category)
            ->setParameter('status', FinanceEntryStatus::Paid)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The memberships the entries of a recurrence were generated for, as plain ids.
     *
     * A FinanceRecurrence carries no owner column: a personal one is owned by whoever it planned its
     * entries for, and that is only readable from those entries. Ids rather than entities because the
     * only caller compares them, and because ORM 3 refuses to select a joined alias on its own.
     *
     * @return string[]
     */
    public function findMemberIdsByRecurrence(FinanceRecurrence $recurrence): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.member) AS member_id')
            ->where('e.recurrence = :recurrence')
            ->andWhere('e.member IS NOT NULL')
            ->setParameter('recurrence', $recurrence)
            ->groupBy('e.member')
            ->getQuery()
            ->getArrayResult();

        $memberIds = [];
        foreach ($rows as $row) {
            $memberIds[] = (string) $row['member_id'];
        }

        return $memberIds;
    }

    /**
     * Drops the entries a recurrence had already planned, either all of them (the recurrence itself is
     * being deleted) or only those past a date (the recurrence was shortened, or switched off because
     * the member it belongs to left the band).
     *
     * Only Planned entries go: a Committed or Paid entry is an occurrence somebody has already acted on
     * and stops belonging to the recurrence.
     *
     * Deliberately a bulk DQL delete rather than an ORM remove(): a stopped recurrence can carry years
     * of planned rows and hydrating them only to delete them is pointless work. The consequence the
     * caller has to know about is that no lifecycle event fires and already-loaded FinanceEntry objects
     * stay in Doctrine's identity map, so anything re-reading them in the same process still sees them.
     * The file attachments of those entries have no foreign key either, so the caller has to clear them
     * itself, through BandSpaceFileSourceDetacher, before calling this.
     *
     * @return int how many entries were dropped
     */
    public function deletePlannedByRecurrence(FinanceRecurrence $recurrence, ?\DateTimeInterface $after = null): int
    {
        $dql = 'DELETE FROM App\Entity\BandSpace\FinanceEntry e
                WHERE e.recurrence = :recurrence
                AND e.status = :status';

        if ($after instanceof \DateTimeInterface) {
            $dql .= ' AND e.date > :after';
        }

        $query = $this->getEntityManager()->createQuery($dql)
            ->setParameter('recurrence', $recurrence)
            ->setParameter('status', FinanceEntryStatus::Planned);

        if ($after instanceof \DateTimeInterface) {
            $query->setParameter('after', $after);
        }

        return (int) $query->execute();
    }

    /**
     * The forecasts a recurrence still owns past a given date, the only entries an edit of that
     * recurrence is allowed to rewrite.
     *
     * @return FinanceEntry[]
     */
    public function findPlannedByRecurrenceAfter(FinanceRecurrence $recurrence, \DateTimeInterface $after): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.member', 'm')->addSelect('m')
            ->where('e.recurrence = :recurrence')
            ->andWhere('e.date > :after')
            ->andWhere('e.status = :status')
            ->setParameter('recurrence', $recurrence)
            ->setParameter('after', $after)
            ->setParameter('status', FinanceEntryStatus::Planned)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Every entry a recurrence has past a given date, whatever its status, so a caller about to
     * materialise occurrences can tell which dates are already taken.
     *
     * @return FinanceEntry[]
     */
    public function findByRecurrenceAfter(FinanceRecurrence $recurrence, \DateTimeInterface $after): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.recurrence = :recurrence')
            ->andWhere('e.date > :after')
            ->setParameter('recurrence', $recurrence)
            ->setParameter('after', $after)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Command palette search. Scoped through the category because a finance entry carries no band
     * space column of its own, and filtered by applyVisibleTo() like every other read path: a
     * personal entry belongs to the member it names, and a search box is exactly the surface that
     * would otherwise show one member's spending to the whole band.
     *
     * @return FinanceEntry[]
     */
    public function searchByBandSpace(BandSpace $bandSpace, BandSpaceMembership $viewer, string $search, int $limit): array
    {
        $qb = $this->createQueryBuilder('e')
            ->addSelect('c')
            ->innerJoin('e.category', 'c')
            ->where('c.bandSpace = :bandSpace')
            ->andWhere('LOWER(e.label) LIKE :search')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('e.date', 'DESC')
            ->setMaxResults($limit);

        $this->applyVisibleTo($qb, $viewer);

        return $qb->getQuery()->getResult();
    }
}
