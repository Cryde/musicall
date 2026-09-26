<?php

declare(strict_types=1);

namespace App\Repository\Search;

use App\Entity\Search\MusicianSearchLog;
use App\Enum\Search\AiSearchOutcome;
use App\Enum\Search\MusicianSearchKind;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<MusicianSearchLog>
 */
class MusicianSearchLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MusicianSearchLog::class);
    }

    /**
     * Written straight through the connection, not persisted: a failed flush closes the entity
     * manager for the rest of the request, and the search that is being recorded still needs it to
     * load its results. This insert can fail on its own without taking the search down.
     */
    public function insert(MusicianSearchLog $log): void
    {
        $this->getEntityManager()->getConnection()->insert('musician_search_log', [
            'id' => $log->id ?? Uuid::uuid4()->toString(),
            'kind' => $log->kind->value,
            'type' => $log->type,
            'instrument_id' => $log->instrument?->id !== null ? (string) $log->instrument->id : null,
            'style_ids' => json_encode($log->styleIds, JSON_THROW_ON_ERROR),
            'location_name' => $log->locationName,
            'latitude' => $log->latitude,
            'longitude' => $log->longitude,
            'ai_query' => $log->aiQuery,
            'ai_outcome' => $log->aiOutcome?->value,
            'first_page_result_count' => $log->firstPageResultCount,
            'visitor_hash' => $log->visitorHash,
            'authenticated' => $log->authenticated,
            'search_datetime' => $log->searchDatetime,
        ], [
            'authenticated' => Types::BOOLEAN,
            'search_datetime' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    /**
     * The (type, instrument, city) filters searches that enough different people ran since `$since`,
     * most people first. Counted in visitors rather than searches, so one person repeating a search
     * cannot put it on the homepage; the coordinates are averaged, as the same city picked twice
     * comes back with the same point.
     *
     * @return list<array{type: int, instrumentId: string, instrumentName: string, locationName: string, latitude: float, longitude: float, visitors: int}>
     */
    public function findFrequentSearches(DateTimeImmutable $since, int $minVisitors, int $limit): array
    {
        $rows = $this->createQueryBuilder('log')
            ->select(
                'log.type AS type',
                'instrument.id AS instrumentId',
                'instrument.musicianName AS instrumentName',
                'log.locationName AS locationName',
                'AVG(log.latitude) AS latitude',
                'AVG(log.longitude) AS longitude',
                'COUNT(DISTINCT log.visitorHash) AS visitors',
            )
            ->join('log.instrument', 'instrument')
            ->where('log.kind = :kind')
            ->andWhere('log.searchDatetime >= :since')
            ->andWhere('log.type IS NOT NULL')
            ->andWhere('log.locationName IS NOT NULL')
            ->andWhere('log.latitude IS NOT NULL')
            ->andWhere('log.longitude IS NOT NULL')
            ->groupBy('log.type, instrument.id, instrument.musicianName, log.locationName')
            ->having('COUNT(DISTINCT log.visitorHash) >= :minVisitors')
            ->orderBy('visitors', 'DESC')
            ->addOrderBy('instrumentName', 'ASC')
            ->setParameter('kind', MusicianSearchKind::Filters)
            ->setParameter('since', $since)
            ->setParameter('minVisitors', $minVisitors)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $row): array => [
            'type' => (int) $row['type'],
            'instrumentId' => (string) $row['instrumentId'],
            'instrumentName' => (string) $row['instrumentName'],
            'locationName' => (string) $row['locationName'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'visitors' => (int) $row['visitors'],
        ], $rows));
    }

    /**
     * Searches of one kind per day, for the admin chart. Raw SQL for the DATE() grouping, which DQL
     * does not have.
     *
     * @return array<int, array{date_label: string, count: int}>
     */
    public function countByDate(MusicianSearchKind $kind, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $result = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT DATE(search_datetime) AS date_label, COUNT(id) AS count
             FROM musician_search_log
             WHERE kind = :kind AND search_datetime >= :from AND search_datetime < :to
             GROUP BY DATE(search_datetime)
             ORDER BY date_label ASC',
            ['kind' => $kind->value, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
        );

        return array_map(
            static fn (array $row): array => ['date_label' => (string) $row['date_label'], 'count' => (int) $row['count']],
            $result->fetchAllAssociative()
        );
    }

    /**
     * How many searches of each kind, how many showed nothing, and what the AI made of its questions.
     *
     * @return array{searches: int, aiSearches: int, zeroResultSearches: int, aiFilters: int, aiNothing: int, aiFailed: int}
     */
    public function summarizeBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $row = $this->createQueryBuilder('log')
            ->select(
                'SUM(CASE WHEN log.kind = :filters THEN 1 ELSE 0 END) AS searches',
                'SUM(CASE WHEN log.kind = :ai THEN 1 ELSE 0 END) AS aiSearches',
                'SUM(CASE WHEN log.kind = :filters AND log.firstPageResultCount = 0 THEN 1 ELSE 0 END) AS zeroResultSearches',
                'SUM(CASE WHEN log.aiOutcome = :outcomeFilters THEN 1 ELSE 0 END) AS aiFilters',
                'SUM(CASE WHEN log.aiOutcome = :outcomeNothing THEN 1 ELSE 0 END) AS aiNothing',
                'SUM(CASE WHEN log.aiOutcome = :outcomeFailed THEN 1 ELSE 0 END) AS aiFailed',
            )
            ->where('log.searchDatetime >= :from')
            ->andWhere('log.searchDatetime < :to')
            ->setParameter('filters', MusicianSearchKind::Filters)
            ->setParameter('ai', MusicianSearchKind::Ai)
            ->setParameter('outcomeFilters', AiSearchOutcome::Filters)
            ->setParameter('outcomeNothing', AiSearchOutcome::Nothing)
            ->setParameter('outcomeFailed', AiSearchOutcome::Failed)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();

        return [
            'searches' => (int) $row['searches'],
            'aiSearches' => (int) $row['aiSearches'],
            'zeroResultSearches' => (int) $row['zeroResultSearches'],
            'aiFilters' => (int) $row['aiFilters'],
            'aiNothing' => (int) $row['aiNothing'],
            'aiFailed' => (int) $row['aiFailed'],
        ];
    }

    /**
     * The filters searches run most, by (type, instrument, city), a missing criterion included as its
     * own row: « batteur, anywhere » is a search people run too.
     *
     * @return list<array{type: ?int, instrumentName: ?string, locationName: ?string, searches: int, visitors: int, zeroResults: int}>
     */
    public function findTopCombinationsBetween(DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
    {
        $rows = $this->createQueryBuilder('log')
            ->select(
                'log.type AS type',
                'instrument.musicianName AS instrumentName',
                'log.locationName AS locationName',
                'COUNT(log.id) AS searches',
                'COUNT(DISTINCT log.visitorHash) AS visitors',
                'SUM(CASE WHEN log.firstPageResultCount = 0 THEN 1 ELSE 0 END) AS zeroResults',
            )
            ->leftJoin('log.instrument', 'instrument')
            ->where('log.kind = :kind')
            ->andWhere('log.searchDatetime >= :from')
            ->andWhere('log.searchDatetime < :to')
            ->groupBy('log.type, instrument.id, instrument.musicianName, log.locationName')
            ->orderBy('searches', 'DESC')
            ->addOrderBy('visitors', 'DESC')
            ->setParameter('kind', MusicianSearchKind::Filters)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $row): array => [
            'type' => $row['type'] !== null ? (int) $row['type'] : null,
            'instrumentName' => $row['instrumentName'] !== null ? (string) $row['instrumentName'] : null,
            'locationName' => $row['locationName'] !== null ? (string) $row['locationName'] : null,
            'searches' => (int) $row['searches'],
            'visitors' => (int) $row['visitors'],
            'zeroResults' => (int) $row['zeroResults'],
        ], $rows));
    }

    /**
     * The AI searches, newest first, for the admin list.
     *
     * @return Paginator<MusicianSearchLog>
     */
    public function findAiSearchesForAdmin(int $offset, int $limit): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('log')
            ->leftJoin('log.instrument', 'instrument')->addSelect('instrument')
            ->where('log.kind = :kind')
            ->setParameter('kind', MusicianSearchKind::Ai)
            ->orderBy('log.searchDatetime', 'DESC')
            ->addOrderBy('log.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return new Paginator($queryBuilder);
    }

    /**
     * A bulk delete, so it loads nothing: these rows have no lifecycle listener nor any file to
     * clean up, and nothing holds them in the identity map when the prune command runs.
     */
    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->delete(MusicianSearchLog::class, 'log')
            ->where('log.searchDatetime < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
