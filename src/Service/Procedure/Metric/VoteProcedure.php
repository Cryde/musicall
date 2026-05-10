<?php declare(strict_types=1);

namespace App\Service\Procedure\Metric;

use App\Contracts\Metric\VotableInterface;
use App\Entity\Metric\VoteCache;
use App\Entity\User;
use App\Repository\Metric\VoteRepository;
use App\Service\Builder\Metric\VoteCacheDirector;
use App\Service\Builder\Metric\VoteDirector;
use App\Service\Identifier\RequestIdentifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class VoteProcedure
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VoteCacheDirector      $voteCacheDirector,
        private readonly VoteRepository         $voteRepository,
        private readonly RequestIdentifier      $requestIdentifier,
        private readonly VoteDirector           $voteDirector,
    ) {
    }

    public function process(VotableInterface $votable, Request $request, int $value, ?User $user = null): void
    {
        $voteCache = $votable->voteCache;
        if (!$voteCache instanceof \App\Entity\Metric\VoteCache) {
            $voteCache = $this->voteCacheDirector->build();
            $votable->voteCache = $voteCache;
            $this->entityManager->persist($voteCache);
            $this->entityManager->flush();
        }

        $identifier = $this->requestIdentifier->fromRequest($request);

        if ($user instanceof \App\Entity\User) {
            $vote = $this->voteRepository->findOneByUserAndVoteCache($user, $voteCache);
        } else {
            $vote = $this->voteRepository->findOneByIdentifierAndVoteCache($identifier, $voteCache);
        }

        if ($vote instanceof \App\Entity\Metric\Vote) {
            if ($vote->value === $value) {
                // Toggle off — remove vote
                $this->adjustCacheCount($voteCache, $vote->value, -1);
                $this->entityManager->remove($vote);
            } else {
                // Change vote direction
                $this->adjustCacheCount($voteCache, $vote->value, -1);
                $vote->value = $value;
                $this->adjustCacheCount($voteCache, $value, 1);
            }
        } else {
            // New vote
            $vote = $this->voteDirector->build(
                $voteCache,
                $identifier,
                $user,
                $value,
                $votable->getVotableType(),
                $votable->getVotableId(),
            );
            $this->entityManager->persist($vote);
            $this->adjustCacheCount($voteCache, $value, 1);
        }

        $this->entityManager->flush();
    }

    private function adjustCacheCount(VoteCache $voteCache, int $value, int $delta): void
    {
        if ($value === 1) {
            $voteCache->upvoteCount += $delta;
        } else {
            $voteCache->downvoteCount += $delta;
        }
    }
}
