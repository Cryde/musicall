<?php

declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumPostReport;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<ForumPostReport>
 */
class ForumPostReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPostReport::class);
    }

    public function findOneByPostAndReporter(ForumPost $post, User $reporter): ?ForumPostReport
    {
        return $this->findOneBy(['post' => $post, 'reporter' => $reporter]);
    }

    /**
     * The id comes straight off the URL, so it is not necessarily a uuid at all. find() would hand a
     * malformed string to the uuid type and throw before the caller could turn a miss into a 404, which
     * answers garbage with a 500. Reject it here instead and let the caller treat it as not found.
     */
    public function findOneById(string $id): ?ForumPostReport
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->find($id);
    }

    /**
     * Oldest first: the moderation queue is worked front to back.
     *
     * @return ForumPostReport[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.post', 'p')
            ->innerJoin('p.topic', 't')
            ->innerJoin('p.creator', 'c')
            ->innerJoin('r.reporter', 'reporter')
            ->addSelect('p', 't', 'c', 'reporter')
            ->where('r.resolvedDatetime IS NULL')
            ->orderBy('r.creationDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
