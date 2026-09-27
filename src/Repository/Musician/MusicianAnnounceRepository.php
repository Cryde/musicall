<?php declare(strict_types=1);

namespace App\Repository\Musician;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Model\Search\MusicianSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MusicianAnnounce>
 */
class MusicianAnnounceRepository extends ServiceEntityRepository
{
    /** Metres between the searched point and an announce. */
    private const string DISTANCE = 'ST_Distance_Sphere(ST_GeomFromText(:point), ST_POINT(musician_announce.longitude, musician_announce.latitude))';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MusicianAnnounce::class);
    }

    /**
     * Eager-loads the last announces for the public "last" listing without the N+1.
     *
     * The ToOne `instrument` is fetch-joined in the limited query (safe with a SQL
     * LIMIT). The ToMany `styles` collections are initialised in a second batched
     * query — fetch-joining a collection together with `setMaxResults()` breaks
     * Doctrine's row hydration, and EXTRA_LAZY would still load one collection per
     * row, whereas a single `WHERE announce IN (...)` is one round-trip.
     *
     * The `author` is intentionally left unhydrated here; it is projected separately
     * by {@see self::findAuthorsDataForAnnounces()} to avoid dragging in the three
     * inverse one-to-one profile tables that a full User hydration force-loads.
     *
     * @return MusicianAnnounce[]
     */
    public function findLastAnnounces(int $limit, ?int $type = null): array
    {
        $queryBuilder = $this->createQueryBuilder('announce')
            ->addSelect('instrument')
            ->leftJoin('announce.instrument', 'instrument')
            ->orderBy('announce.creationDatetime', 'DESC')
            ->setMaxResults($limit);
        if ($type !== null) {
            $queryBuilder->andWhere('announce.type = :type')->setParameter('type', $type);
        }
        $announces = $queryBuilder->getQuery()->getResult();

        if ($announces === []) {
            return [];
        }

        $this->initializeStyles($announces);

        return $announces;
    }

    /**
     * An author's latest announces still young enough to be matched (#1082), newest first.
     *
     * @return MusicianAnnounce[]
     */
    public function findRecentByAuthor(User $author, \DateTimeInterface $since, int $limit): array
    {
        $announces = $this->createQueryBuilder('announce')
            ->addSelect('instrument')
            ->join('announce.instrument', 'instrument')
            ->where('announce.author = :author')
            ->andWhere('announce.creationDatetime >= :since')
            ->setParameter('author', $author)
            ->setParameter('since', $since)
            ->orderBy('announce.creationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        $this->initializeStyles($announces);

        return $announces;
    }

    /**
     * The announces that answer this one (#1082): the mirror type, the same instrument, within the
     * radius and young enough, by somebody else whose account still exists. The closest ones, with
     * their styles loaded so the caller can rank by the styles they share.
     *
     * @return list<array{0: MusicianAnnounce, distance: float}>
     */
    public function findAnswering(MusicianAnnounce $announce, \DateTimeInterface $since, int $radiusKm, int $limit): array
    {
        $mirrorType = $announce->type === MusicianAnnounce::TYPE_MUSICIAN ? MusicianAnnounce::TYPE_BAND : MusicianAnnounce::TYPE_MUSICIAN;

        /** @var list<array{0: MusicianAnnounce, distance: float}> $rows */
        $rows = $this->createQueryBuilder('musician_announce')
            ->addSelect('instrument', 'author')
            ->addSelect(self::DISTANCE . ' as distance')
            ->join('musician_announce.instrument', 'instrument')
            ->join('musician_announce.author', 'author')
            ->where('musician_announce.type = :type')
            ->andWhere('musician_announce.instrument = :instrument')
            ->andWhere('musician_announce.author != :author')
            ->andWhere('author.deletionDatetime IS NULL')
            ->andWhere('musician_announce.creationDatetime >= :since')
            ->andWhere(self::DISTANCE . ' <= :radius')
            ->setParameter('type', $mirrorType)
            ->setParameter('instrument', $announce->instrument)
            ->setParameter('author', $announce->author)
            ->setParameter('since', $since)
            ->setParameter('point', 'POINT(' . $announce->longitude . ' ' . $announce->latitude . ')')
            ->setParameter('radius', $radiusKm * 1000)
            ->orderBy('distance', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        $this->initializeStyles(array_map(static fn (array $row): MusicianAnnounce => $row[0], $rows));

        return $rows;
    }

    /**
     * Loads the styles of these announces in one query. A fetch join cannot sit with a SQL LIMIT, and
     * lazy loading would query once per announce.
     *
     * @param MusicianAnnounce[] $announces
     */
    private function initializeStyles(array $announces): void
    {
        if ($announces === []) {
            return;
        }
        $this->createQueryBuilder('announce')
            ->addSelect('styles')
            ->leftJoin('announce.styles', 'styles')
            ->where('announce IN (:announces)')
            ->setParameter('announces', $announces)
            ->getQuery()
            ->getResult();
    }

    /**
     * Projects exactly the author fields the "last" listing needs, keyed by announce id.
     *
     * Everything is selected as scalars, so no User entity is hydrated and its inverse
     * one-to-one profile tables (`notificationPreference` / `teacherProfile`) never load.
     * The profile picture is reduced to its `imageName` (enough to rebuild the asset URL)
     * and the `musicianProfile` to its id (existence flag).
     *
     * @param MusicianAnnounce[] $announces
     *
     * @return array<string, array{id: string, username: string, deletionDatetime: ?\DateTimeImmutable, hasMusicianProfile: bool, profilePictureName: ?string}>
     */
    public function findAuthorsDataForAnnounces(array $announces): array
    {
        if ($announces === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('announce')
            ->select(
                'announce.id AS announceId',
                'author.id AS authorId',
                'author.username AS username',
                'author.deletionDatetime AS deletionDatetime',
                'musicianProfile.id AS musicianProfileId',
                'picture.imageName AS profilePictureName',
            )
            ->join('announce.author', 'author')
            ->leftJoin('author.profilePicture', 'picture')
            ->leftJoin('author.musicianProfile', 'musicianProfile')
            ->where('announce IN (:announces)')
            ->setParameter('announces', $announces)
            ->getQuery()
            ->getResult();

        $authorsByAnnounceId = [];
        foreach ($rows as $row) {
            $authorsByAnnounceId[(string) $row['announceId']] = [
                'id' => (string) $row['authorId'],
                'username' => (string) $row['username'],
                'deletionDatetime' => $row['deletionDatetime'],
                'hasMusicianProfile' => $row['musicianProfileId'] !== null,
                'profilePictureName' => $row['profilePictureName'],
            ];
        }

        return $authorsByAnnounceId;
    }

    /**
     * @return array<int, array{date_label: string, count: int}>
     */
    public function countMusicianAnnouncesByDate(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $result = $conn->executeQuery(
            'SELECT DATE(creation_datetime) AS date_label, COUNT(id) AS count
             FROM musician_announce
             WHERE creation_datetime >= :from AND creation_datetime < :to
             GROUP BY DATE(creation_datetime)
             ORDER BY date_label ASC',
            ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
        );

        return array_map(
            fn (array $row): array => ['date_label' => $row['date_label'], 'count' => (int) $row['count']],
            $result->fetchAllAssociative()
        );
    }

    /**
     * @return array<string, int>
     */
    public function countByTypeBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $results = $this->createQueryBuilder('ma')
            ->select('ma.type, COUNT(ma.id) as count')
            ->where('ma.creationDatetime >= :from')
            ->andWhere('ma.creationDatetime < :to')
            ->groupBy('ma.type')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $label = $row['type'] === MusicianAnnounce::TYPE_MUSICIAN ? 'musician' : 'band';
            $counts[$label] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    public function findTopInstrumentsBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 5): array
    {
        $results = $this->createQueryBuilder('ma')
            ->select('i.name, COUNT(ma.id) as count')
            ->join('ma.instrument', 'i')
            ->where('ma.creationDatetime >= :from')
            ->andWhere('ma.creationDatetime < :to')
            ->groupBy('i.id')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        return array_map(
            fn (array $row): array => ['name' => $row['name'], 'count' => (int) $row['count']],
            $results
        );
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    public function findTopStylesBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 5): array
    {
        $results = $this->createQueryBuilder('ma')
            ->select('s.name, COUNT(ma.id) as count')
            ->join('ma.styles', 's')
            ->where('ma.creationDatetime >= :from')
            ->andWhere('ma.creationDatetime < :to')
            ->groupBy('s.id')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        return array_map(
            fn (array $row): array => ['name' => $row['name'], 'count' => (int) $row['count']],
            $results
        );
    }

    /**
     * @return array<int, MusicianAnnounce|array{0: MusicianAnnounce, distance: float}>
     */
    public function findByCriteria(MusicianSearch $musician, ?User $currentUser, int $limit = 12): array
    {
        $qb = $this->createQueryBuilder('musician_announce')
            ->select('musician_announce')
            ->addSelect('instrument')
            ->addSelect('author')
            ->join('musician_announce.instrument', 'instrument')
            ->join('musician_announce.author', 'author')
            ->orderBy('musician_announce.creationDatetime', 'DESC')
            ->setMaxResults($limit);

        // Calculate offset for pagination
        $offset = ($musician->page - 1) * $musician->limit;
        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        $this->applyCriteria($qb, $musician, $currentUser);

        if ($this->hasPoint($musician)) {
            $qb->addSelect(self::DISTANCE . ' as distance')
                ->setParameter('point', $this->pointOf($musician))
                ->orderBy('distance', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Whether a search would find anything at all, without counting it (#1084): the guided search
     * offers a wider search only when it would show something.
     */
    public function hasAnyByCriteria(MusicianSearch $musician, ?User $currentUser): bool
    {
        $qb = $this->createQueryBuilder('musician_announce')
            ->select('musician_announce.id')
            ->setMaxResults(1);
        $this->applyCriteria($qb, $musician, $currentUser);

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    private function hasPoint(MusicianSearch $musician): bool
    {
        return $musician->latitude && $musician->longitude;
    }

    private function pointOf(MusicianSearch $musician): string
    {
        return 'POINT(' . $musician->longitude . ' ' . $musician->latitude . ')';
    }

    private function applyCriteria(QueryBuilder $qb, MusicianSearch $musician, ?User $currentUser): void
    {
        if ($musician->type !== null) {
            $qb->andWhere('musician_announce.type = :type')
                ->setParameter('type', $musician->type);
        }

        if ($musician->instrument instanceof \App\Entity\Attribute\Instrument) {
            $qb->andWhere('musician_announce.instrument = :instrument')
                ->setParameter('instrument', $musician->instrument);
        }

        if ($currentUser instanceof \App\Entity\User) {
            $qb->andWhere('musician_announce.author != :current_user')
                ->setParameter('current_user', $currentUser);
        }

        // Any of the styles, as a subquery rather than a join: a join returns an announce once per
        // matching style, so one with two of them took two places in the page.
        if ($styles = $musician->styles) {
            $qb->andWhere('EXISTS (SELECT 1 FROM ' . MusicianAnnounce::class . ' styled JOIN styled.styles styled_style WHERE styled = musician_announce AND styled_style IN (:styles))')
                ->setParameter('styles', $styles);
        }

        if ($musician->radius !== null && $this->hasPoint($musician)) {
            $qb->andWhere(self::DISTANCE . ' <= :radius')
                ->setParameter('point', $this->pointOf($musician))
                ->setParameter('radius', $musician->radius * 1000);
        }
    }
}
