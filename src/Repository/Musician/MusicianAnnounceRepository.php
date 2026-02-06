<?php declare(strict_types=1);

namespace App\Repository\Musician;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Model\Search\MusicianSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MusicianAnnounce>
 */
class MusicianAnnounceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MusicianAnnounce::class);
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
            fn (array $row) => ['date_label' => $row['date_label'], 'count' => (int) $row['count']],
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
            fn (array $row) => ['name' => $row['name'], 'count' => (int) $row['count']],
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
            fn (array $row) => ['name' => $row['name'], 'count' => (int) $row['count']],
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

        // Filter by type if provided
        if ($musician->type !== null) {
            $qb->andWhere('musician_announce.type = :type')
                ->setParameter('type', $musician->type);
        }

        // Filter by instrument if provided
        if ($musician->instrument !== null) {
            $qb->andWhere('musician_announce.instrument = :instrument')
                ->setParameter('instrument', $musician->instrument);
        }

        if ($currentUser) {
            $qb->andWhere('musician_announce.author != :current_user')
                ->setParameter('current_user', $currentUser);
        }

        if ($styles = $musician->styles) {
            $qb->leftJoin('musician_announce.styles', 'styles')
                ->andWhere('styles IN (:styles)')
                ->setParameter('styles', $styles);
        }

        if ($musician->latitude && $musician->longitude) {
            $qb->addSelect("
            ST_Distance_Sphere(ST_GeomFromText(:point), ST_POINT(musician_announce.longitude, musician_announce.latitude)) as distance
            ")
                ->setParameter('point', 'POINT(' . $musician->longitude . ' ' . $musician->latitude . ')')
                ->orderBy('distance', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }
}
