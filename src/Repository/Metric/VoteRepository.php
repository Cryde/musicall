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

    /**
     * @param int[] $voteCacheIds
     * @return array<int, int>  vote_cache_id => value
     */
    public function findValuesByUserAndVoteCacheIds(User $user, array $voteCacheIds): array
    {
        if ($voteCacheIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('vote')
            ->select('IDENTITY(vote.voteCache) AS vote_cache_id, vote.value')
            ->where('vote.user = :user')
            ->andWhere('vote.voteCache IN (:vote_cache_ids)')
            ->setParameter('user', $user)
            ->setParameter('vote_cache_ids', $voteCacheIds)
            ->getQuery()
            ->getArrayResult();

        $byCacheId = [];
        foreach ($rows as $row) {
            $byCacheId[(int) $row['vote_cache_id']] = (int) $row['value'];
        }

        return $byCacheId;
    }

    /**
     * @param int[] $voteCacheIds
     * @return array<int, int>  vote_cache_id => value
     */
    public function findValuesByIdentifierAndVoteCacheIds(string $identifier, array $voteCacheIds): array
    {
        if ($voteCacheIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('vote')
            ->select('IDENTITY(vote.voteCache) AS vote_cache_id, vote.value')
            ->where('vote.identifier = :identifier')
            ->andWhere('vote.user IS NULL')
            ->andWhere('vote.voteCache IN (:vote_cache_ids)')
            ->setParameter('identifier', $identifier)
            ->setParameter('vote_cache_ids', $voteCacheIds)
            ->getQuery()
            ->getArrayResult();

        $byCacheId = [];
        foreach ($rows as $row) {
            $byCacheId[(int) $row['vote_cache_id']] = (int) $row['value'];
        }

        return $byCacheId;
    }
}
