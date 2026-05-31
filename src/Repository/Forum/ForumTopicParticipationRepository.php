<?php

declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\ForumTopic;
use App\Entity\Forum\ForumTopicParticipation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumTopicParticipation>
 */
class ForumTopicParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumTopicParticipation::class);
    }

    public function findOneByUserAndTopic(User $user, ForumTopic $topic): ?ForumTopicParticipation
    {
        return $this->findOneBy(['user' => $user, 'topic' => $topic]);
    }

    public function createQueryBuilderForUser(User $user, bool $includeRemoved = false): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.topic', 't')
            ->leftJoin('t.lastPost', 'lp')
            ->leftJoin('lp.creator', 'lpc')
            ->innerJoin('t.author', 'a')
            ->innerJoin('t.forum', 'f')
            ->addSelect('t', 'lp', 'lpc', 'a', 'f')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('lp.creationDatetime', 'DESC')
            ->addOrderBy('p.creationDatetime', 'DESC');

        if (!$includeRemoved) {
            $qb->andWhere('p.removedDatetime IS NULL');
        }

        return $qb;
    }

    /**
     * Active participants (removedDatetime IS NULL) of a topic, as User entities.
     *
     * @return User[]
     */
    public function findActiveParticipantUsersByTopic(ForumTopic $topic): array
    {
        $participations = $this->createQueryBuilder('p')
            ->innerJoin('p.user', 'u')
            ->addSelect('u')
            ->where('p.topic = :topic')
            ->andWhere('p.removedDatetime IS NULL')
            ->setParameter('topic', $topic)
            ->getQuery()
            ->getResult();

        return array_map(static fn (ForumTopicParticipation $participation): User => $participation->user, $participations);
    }
}
