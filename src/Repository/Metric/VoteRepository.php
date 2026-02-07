<?php declare(strict_types=1);

namespace App\Repository\Metric;

use App\Entity\Metric\Vote;
use App\Entity\Metric\VoteCache;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function findOneByUserAndVoteCache(User $user, VoteCache $voteCache): ?Vote
    {
        return $this->createQueryBuilder('vote')
            ->where('vote.voteCache = :vote_cache')
            ->andWhere('vote.user = :user')
            ->setParameter('vote_cache', $voteCache)
            ->setParameter('user', $user)
            ->getQuery()
            ->setMaxResults(1)
            ->getResult()[0] ?? null;
    }

    public function findOneByIdentifierAndVoteCache(string $identifier, VoteCache $voteCache): ?Vote
    {
        return $this->createQueryBuilder('vote')
            ->where('vote.voteCache = :vote_cache')
            ->andWhere('vote.identifier = :identifier')
            ->andWhere('vote.user IS NULL')
            ->setParameter('vote_cache', $voteCache)
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->setMaxResults(1)
            ->getResult()[0] ?? null;
    }
}
