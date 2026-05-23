<?php

declare(strict_types=1);

namespace App\Service\Metric;

use App\Entity\Publication;
use App\Entity\User;
use App\Repository\Metric\VoteRepository;
use App\Service\Identifier\RequestIdentifier;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class PublicationUserVoteResolver
{
    public function __construct(
        private VoteRepository    $voteRepository,
        private Security          $security,
        private RequestIdentifier $requestIdentifier,
        private RequestStack      $requestStack,
    ) {
    }

    /**
     * @param Publication[] $publications
     *
     * @return array<int, int> vote_cache_id => -1|1
     */
    public function resolveForPublications(array $publications): array
    {
        $ids = [];
        foreach ($publications as $publication) {
            $cacheId = $publication->voteCache?->id;
            if ($cacheId !== null) {
                $ids[] = $cacheId;
            }
        }

        return $this->resolve($ids);
    }

    /**
     * @param int[] $voteCacheIds
     *
     * @return array<int, int> vote_cache_id => -1|1
     */
    private function resolve(array $voteCacheIds): array
    {
        if ($voteCacheIds === []) {
            return [];
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if ($user instanceof User) {
            return $this->voteRepository->findValuesByUserAndVoteCacheIds($user, $voteCacheIds);
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return [];
        }

        $identifier = $this->requestIdentifier->fromRequest($request);

        return $this->voteRepository->findValuesByIdentifierAndVoteCacheIds($identifier, $voteCacheIds);
    }
}
