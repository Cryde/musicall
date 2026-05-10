<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\ForumPostResource;
use App\Entity\Forum\ForumPost;
use App\Entity\User;
use App\Repository\Forum\ForumPostRepository;
use App\Repository\Metric\VoteRepository;
use App\Service\Builder\Forum\ForumPostBuilder;
use App\Service\Identifier\RequestIdentifier;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<ForumPostResource>
 */
readonly class ForumPostCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ForumPostRepository $forumPostRepository,
        private VoteRepository      $voteRepository,
        private ForumPostBuilder    $forumPostBuilder,
        private Pagination          $pagination,
        private Security            $security,
        private RequestIdentifier   $requestIdentifier,
        private RequestStack        $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $topicSlug = $uriVariables['slug'];

        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $qb = $this->forumPostRepository->createQueryBuilderByTopicSlug($topicSlug);
        $qb->setFirstResult($offset)
           ->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb->getQuery());
        $totalItems = count($paginator);

        /** @var ForumPost[] $posts */
        $posts = iterator_to_array($paginator);

        $voteCacheIds = [];
        foreach ($posts as $post) {
            $cacheId = $post->voteCache?->id;
            if ($cacheId !== null) {
                $voteCacheIds[] = $cacheId;
            }
        }

        $userVotesByCacheId = $this->fetchUserVotes($voteCacheIds);
        $dtos = $this->forumPostBuilder->buildList($posts, $userVotesByCacheId);

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $page,
            $itemsPerPage,
            $totalItems,
        );
    }

    /**
     * @param int[] $voteCacheIds
     * @return array<int, int>
     */
    private function fetchUserVotes(array $voteCacheIds): array
    {
        if ($voteCacheIds === []) {
            return [];
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if ($user) {
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
