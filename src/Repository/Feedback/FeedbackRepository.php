<?php declare(strict_types=1);

namespace App\Repository\Feedback;

use App\Entity\Feedback\Feedback;
use App\Enum\Feedback\FeedbackModule;
use App\Enum\Feedback\FeedbackStatus;
use App\Enum\Feedback\FeedbackType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<Feedback>
 */
class FeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feedback::class);
    }

    /**
     * Looked up through a QueryBuilder rather than find(), which coerces the id through the uuid
     * field type and throws on a malformed one. The id here is a URI path segment, so nothing has
     * validated it before it arrives, and a stray character has to be a 404 rather than a 500.
     */
    public function findOneById(string $id): ?Feedback
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->createQueryBuilder('f')
            ->where('f.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The number the admin badge shows. Counts untriaged reports rather than recent ones, so reading
     * one and deciding it needs nothing still clears it.
     */
    public function countNew(): int
    {
        return $this->count(['status' => FeedbackStatus::New]);
    }

    /**
     * The admin triage list, newest first.
     *
     * Paginated in the database rather than in the browser, unlike the other admin collections: this
     * is the one admin table nothing prunes, so it only ever grows.
     *
     * Joins the author and the band space eagerly because the list renders both on every row, and a
     * lazy proxy per row would be a pair of N+1s.
     *
     * @return Paginator<Feedback>
     */
    public function findForAdmin(
        ?FeedbackStatus $status,
        ?FeedbackModule $module,
        ?FeedbackType $type,
        int $offset,
        int $limit,
    ): Paginator {
        $queryBuilder = $this->createQueryBuilder('f')
            ->leftJoin('f.user', 'u')->addSelect('u')
            ->leftJoin('f.bandSpace', 'bs')->addSelect('bs')
            ->orderBy('f.creationDatetime', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($status instanceof FeedbackStatus) {
            $queryBuilder->andWhere('f.status = :status')->setParameter('status', $status);
        }
        if ($module instanceof FeedbackModule) {
            $queryBuilder->andWhere('f.module = :module')->setParameter('module', $module);
        }
        if ($type instanceof FeedbackType) {
            $queryBuilder->andWhere('f.type = :type')->setParameter('type', $type);
        }

        return new Paginator($queryBuilder->getQuery(), fetchJoinCollection: false);
    }
}
