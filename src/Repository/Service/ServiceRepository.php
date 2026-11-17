<?php

declare(strict_types=1);

namespace App\Repository\Service;

use App\Entity\Attribute\Instrument;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceInstrument;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\User;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServicePlaceType;
use App\Enum\Service\ServiceStatus;
use App\Enum\Service\TeachingSearchOrder;
use App\Enum\Teacher\AgeGroup;
use App\Enum\Teacher\StudentLevel;
use App\Model\Search\ServiceSearch;
use App\Model\Search\TeachingSearch;
use App\Repository\User\Relation\UserBlockRepository;
use App\Repository\UserRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    /** Metres between the searched point and the place behind `%1$s`. */
    private const string PLACE_DISTANCE = 'ST_Distance_Sphere(ST_GeomFromText(:point), ST_POINT(%1$s.longitude, %1$s.latitude))';

    /** A teacher's lesson compared at 30 minutes, whatever its length (#1314). */
    private const int TEACHING_PRICE_MINUTES = 30;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /** The DQL twin of Service::isPublished(), for every query a visitor reads: a draft is its owner's only (#1406). */
    public static function published(string $alias): string
    {
        return sprintf("%s.status = '%s'", $alias, ServiceStatus::Published->value);
    }

    public function findOneByUserAndKind(User $user, ServiceKind $kind): ?Service
    {
        return $this->findOneBy(['user' => $user, 'kind' => $kind]);
    }

    /** What a visitor may see: a draft is its owner's only (#1406). */
    public function findPublishedOneByUserAndKind(User $user, ServiceKind $kind): ?Service
    {
        return $this->findOneBy(['user' => $user, 'kind' => $kind, 'status' => ServiceStatus::Published]);
    }

    /**
     * The member's services, oldest first, for their « Mes services » page.
     *
     * @return list<Service>
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['creationDatetime' => 'ASC']);
    }

    public function findPublishedOneByUsernameAndKind(string $username, ServiceKind $kind): ?Service
    {
        return $this->createQueryBuilder('service')
            ->innerJoin('service.user', 'u')
            ->where('u.username = :username')
            ->andWhere('service.kind = :kind')
            ->andWhere(self::published('service'))
            ->setParameter('username', $username)
            ->setParameter('kind', $kind)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Service>
     */
    public function findRecentByKind(ServiceKind $kind, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array
    {
        return $this->createQueryBuilder('service')
            ->innerJoin('service.user', 'u')
            ->addSelect('u')
            ->where('service.kind = :kind')
            ->andWhere('service.creationDatetime >= :from')
            ->andWhere('service.creationDatetime < :to')
            ->setParameter('kind', $kind)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('service.creationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Service>
     */
    public function findPublishedWithInstrumentsByKind(ServiceKind $kind, ?User $viewer = null): array
    {
        $queryBuilder = $this->createQueryBuilder('service')
            ->innerJoin('service.user', 'u')
            ->addSelect('u')
            ->leftJoin('service.instruments', 'i')
            ->addSelect('i')
            ->leftJoin('i.instrument', 'instr')
            ->addSelect('instr')
            ->where('service.kind = :kind')
            ->andWhere(self::published('service'))
            ->andWhere(UserRepository::publiclyVisible('u'))
            ->setParameter('kind', $kind)
            ->orderBy('service.creationDatetime', 'ASC');
        if ($viewer instanceof User) {
            $queryBuilder->andWhere(UserBlockRepository::notBlockedEitherWay('u', 'viewer'))
                ->setParameter('viewer', $viewer);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * The instruments the teacher search can find a teacher for, which its instrument filter offers.
     *
     * @return list<Instrument>
     */
    public function findTaughtInstruments(): array
    {
        $taught = $this->createQueryBuilder('service')
            ->select('1')
            ->innerJoin('service.instruments', 'taught')
            ->innerJoin('service.user', 'u')
            ->innerJoin('u.profile', 'profile')
            ->where('taught.instrument = instrument')
            ->andWhere('service.kind = :kind');
        $this->listed($taught);

        return $this->getEntityManager()->createQueryBuilder()
            ->select('instrument')
            ->from(Instrument::class, 'instrument')
            ->where(sprintf('EXISTS (%s)', $taught->getDQL()))
            ->setParameter('kind', ServiceKind::Teaching)
            ->orderBy('instrument.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * One page of the teacher search, in its order: each service's id, and its distance in metres when the search has
     * a point and the teacher a place in reach (null for a teacher found online only).
     *
     * @return list<array{id: string, distance: float|null}>
     */
    public function findTeachingSearchPage(TeachingSearch $search, ?User $viewer): array
    {
        $service = $search->service;
        $queryBuilder = $this->teachingSearch($search, $viewer)
            ->select('service.id AS id')
            ->setFirstResult($service->offset())
            ->setMaxResults($service->limit);
        if ($service->hasPoint()) {
            $queryBuilder->addSelect(sprintf('(SELECT MIN(%s) FROM %s nearest WHERE nearest.service = service AND %s) AS distance', sprintf(self::PLACE_DISTANCE, 'nearest'), ServicePlace::class, $this->measured('nearest', $service)))
                ->setParameter('point', $service->point());
        }
        $this->orderTeachingSearch($queryBuilder, $search);

        return array_values(array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'distance' => isset($row['distance']) ? (float) $row['distance'] : null,
        ], $queryBuilder->getQuery()->getScalarResult()));
    }

    public function countTeachingSearch(TeachingSearch $search, ?User $viewer): int
    {
        return (int) $this->teachingSearch($search, $viewer)
            ->select('COUNT(service.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The services of a search page with what their cards show, keyed by id. Each collection comes in a query of
     * its own: fetch joining them all at once would multiply the rows by one another.
     *
     * @param list<string> $ids
     *
     * @return array<string, Service>
     */
    public function findForSearchCards(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<Service> $services */
        $services = $this->createQueryBuilder('service')
            ->addSelect('u', 'profile', 'picture')
            ->innerJoin('service.user', 'u')
            ->innerJoin('u.profile', 'profile')
            ->leftJoin('u.profilePicture', 'picture')
            ->where('service.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
        $this->createQueryBuilder('service')
            ->addSelect('taught', 'instrument')
            ->leftJoin('service.instruments', 'taught')
            ->leftJoin('taught.instrument', 'instrument')
            ->where('service.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
        foreach (['styles', 'offers', 'places', 'media'] as $collection) {
            $this->createQueryBuilder('service')
                ->addSelect('item')
                ->leftJoin('service.' . $collection, 'item')
                ->where('service.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();
        }

        $byId = [];
        foreach ($services as $found) {
            $byId[(string) $found->id] = $found;
        }

        return $byId;
    }

    /**
     * The teacher search's filters: the shared ones of every kind, then the instrument, level, age group and free
     * trial only teaching has.
     */
    private function teachingSearch(TeachingSearch $search, ?User $viewer): QueryBuilder
    {
        $queryBuilder = $this->serviceSearch(ServiceKind::Teaching, $search->service, $viewer);

        if ($search->instrument instanceof Instrument) {
            $queryBuilder->andWhere('EXISTS (SELECT 1 FROM ' . ServiceInstrument::class . ' taught WHERE taught.service = service AND taught.instrument = :instrument)')
                ->setParameter('instrument', $search->instrument);
        }
        if ($search->level instanceof StudentLevel) {
            $queryBuilder->andWhere('JSON_CONTAINS(service.details, :level) = 1')
                ->setParameter('level', json_encode(['student_levels' => [$search->level->value]], JSON_THROW_ON_ERROR));
        }
        if ($search->ageGroup instanceof AgeGroup) {
            $queryBuilder->andWhere('JSON_CONTAINS(service.details, :age_group) = 1')
                ->setParameter('age_group', json_encode(['age_groups' => [$search->ageGroup->value]], JSON_THROW_ON_ERROR));
        }
        if ($search->freeTrial) {
            $queryBuilder->andWhere($this->freeTrial($queryBuilder));
        }

        return $queryBuilder;
    }

    /**
     * Its order, then the nearest first when there is a point, the newest otherwise, then the id so that two pages
     * never share a teacher.
     */
    private function orderTeachingSearch(QueryBuilder $queryBuilder, TeachingSearch $search): void
    {
        $service = $search->service;
        if ($search->order === TeachingSearchOrder::Price) {
            $lessons = static fn (string $alias): string => sprintf(
                "FROM %2\$s %1\$s WHERE %1\$s.service = service AND %1\$s.unit = '%3\$s' AND %1\$s.quantity > 0",
                $alias,
                ServiceOffer::class,
                OfferUnit::Lesson->value,
            );
            $queryBuilder->addSelect(sprintf('(SELECT MIN(lesson.price * %d / lesson.quantity) %s) AS HIDDEN lesson_price', self::TEACHING_PRICE_MINUTES, $lessons('lesson')))
                ->addSelect(sprintf('CASE WHEN EXISTS (SELECT 1 %s) THEN 0 ELSE 1 END AS HIDDEN unpriced', $lessons('priced')))
                ->addOrderBy('unpriced', 'ASC')
                ->addOrderBy('lesson_price', 'ASC');
        } elseif ($search->order === TeachingSearchOrder::FreeTrial) {
            $queryBuilder->addSelect(sprintf('CASE WHEN %s THEN 0 ELSE 1 END AS HIDDEN paid_trial', $this->freeTrial($queryBuilder)))
                ->addOrderBy('paid_trial', 'ASC');
        }

        if ($service->hasPoint()) {
            $this->orderByDistance($queryBuilder, $service);
        }
        $queryBuilder->addOrderBy('service.creationDatetime', 'DESC')
            ->addOrderBy('service.id', 'ASC');
    }

    /** A free trial lesson: offered, at no price (or none said). */
    private function freeTrial(QueryBuilder $queryBuilder): string
    {
        $queryBuilder->setParameter('offers_trial', '{"offers_trial": true}')
            ->setParameter('trial_free', '{"trial_price": 0}')
            ->setParameter('trial_unpriced', '{"trial_price": null}');

        return '(JSON_CONTAINS(service.details, :offers_trial) = 1'
            . ' AND (JSON_CONTAINS(service.details, :trial_free) = 1 OR JSON_CONTAINS(service.details, :trial_unpriced) = 1))';
    }

    /**
     * What every kind's search filters on: its kind, published, by a member anyone may see and who has not blocked
     * the viewer nor been blocked by them, and, with a point and a radius, a place in reach (or online, when asked).
     */
    private function serviceSearch(ServiceKind $kind, ServiceSearch $search, ?User $viewer): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('service')
            ->innerJoin('service.user', 'u')
            ->innerJoin('u.profile', 'profile')
            ->where('service.kind = :kind')
            ->setParameter('kind', $kind);
        $this->listed($queryBuilder);
        if ($viewer instanceof User) {
            $queryBuilder->andWhere(UserBlockRepository::notBlockedEitherWay('u', 'viewer'))
                ->setParameter('viewer', $viewer);
        }

        if ($search->hasPoint() && $search->radius !== null) {
            $queryBuilder->setParameter('point', $search->point())
                ->setParameter('radius', $search->radius * 1000);
            $inReach = sprintf('EXISTS (SELECT 1 FROM %s reached WHERE reached.service = service AND %s)', ServicePlace::class, $this->inReach('reached'));
            if ($search->online) {
                $inReach = sprintf(
                    "(%s OR EXISTS (SELECT 1 FROM %s online WHERE online.service = service AND online.type = '%s'))",
                    $inReach,
                    ServicePlace::class,
                    ServicePlaceType::Remote->value,
                );
            }
            $queryBuilder->andWhere($inReach);
        }

        return $queryBuilder;
    }

    /**
     * What a service needs to be listed at all: published, by a member anyone may see, whose profile is public. Expects
     * the service as `service`, its member as `u` and their profile as `profile`.
     */
    private function listed(QueryBuilder $queryBuilder): void
    {
        $queryBuilder->andWhere(self::published('service'))
            ->andWhere(UserRepository::publiclyVisible('u'))
            // A private profile keeps itself out of every listing.
            ->andWhere('profile.isPublic = true');
    }

    /** The nearest first, a service found online only (no distance) last. */
    private function orderByDistance(QueryBuilder $queryBuilder, ServiceSearch $search): void
    {
        // Within a radius and without online, every service found has a place in reach, so a distance.
        if ($search->radius !== null && !$search->online) {
            $queryBuilder->addOrderBy('distance', 'ASC');

            return;
        }
        $queryBuilder->addSelect(sprintf('CASE WHEN EXISTS (SELECT 1 FROM %s placed WHERE placed.service = service AND %s) THEN 0 ELSE 1 END AS HIDDEN unplaced', ServicePlace::class, $this->measured('placed', $search)))
            ->addOrderBy('unplaced', 'ASC')
            ->addOrderBy('distance', 'ASC');
    }

    /** The places a distance is measured to: those in reach with a radius, every located one without. */
    private function measured(string $alias, ServiceSearch $search): string
    {
        return $search->radius !== null ? $this->inReach($alias) : $this->located($alias);
    }

    /**
     * A place within the searched radius, or, for lessons at the student's, the student within the teacher's own
     * radius (the searched one when the teacher gave none).
     */
    private function inReach(string $alias): string
    {
        $distance = sprintf(self::PLACE_DISTANCE, $alias);

        return sprintf(
            "%1\$s AND ((%2\$s.type = '%3\$s' AND %4\$s <= :radius) OR (%2\$s.type = '%5\$s' AND %4\$s <= COALESCE(%2\$s.radius * 1000, :radius)))",
            $this->located($alias),
            $alias,
            ServicePlaceType::AtMyPlace->value,
            $distance,
            ServicePlaceType::AtClientPlace->value,
        );
    }

    /** A place on the map: not online, and with its coordinates. */
    private function located(string $alias): string
    {
        return sprintf("%1\$s.type != '%2\$s' AND %1\$s.latitude IS NOT NULL AND %1\$s.longitude IS NOT NULL", $alias, ServicePlaceType::Remote->value);
    }
}
