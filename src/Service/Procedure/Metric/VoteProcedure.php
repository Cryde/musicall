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
        $voteCache = $votable->getVoteCache();
        if (!$voteCache) {
            $voteCache = $this->voteCacheDirector->build();
            $votable->setVoteCache($voteCache);
            $this->entityManager->persist($voteCache);
            $this->entityManager->flush();
        }

        $identifier = $this->requestIdentifier->fromRequest($request);

        if ($user) {
            $vote = $this->voteRepository->findOneByUserAndVoteCache($user, $voteCache);
        } else {
            $vote = $this->voteRepository->findOneByIdentifierAndVoteCache($identifier, $voteCache);
        }

        if ($vote) {
            if ($vote->getValue() === $value) {
                // Toggle off — remove vote
                $this->adjustCacheCount($voteCache, $vote->getValue(), -1);
                $this->entityManager->remove($vote);
            } else {
                // Change vote direction
                $this->adjustCacheCount($voteCache, $vote->getValue(), -1);
                $vote->setValue($value);
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
            $voteCache->setUpvoteCount($voteCache->getUpvoteCount() + $delta);
        } else {
            $voteCache->setDownvoteCount($voteCache->getDownvoteCount() + $delta);
        }
    }
}
