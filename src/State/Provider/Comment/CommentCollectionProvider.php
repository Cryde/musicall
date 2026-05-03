<?php declare(strict_types=1);

namespace App\State\Provider\Comment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Comment\CommentResource;
use App\Entity\Comment\Comment;
use App\Entity\User;
use App\Repository\Comment\CommentRepository;
use App\Repository\Metric\VoteRepository;
use App\Service\Builder\Comment\CommentBuilder;
use App\Service\Identifier\RequestIdentifier;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<object>
 */
readonly class CommentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private CommentRepository $commentRepository,
        private VoteRepository $voteRepository,
        private CommentBuilder $commentBuilder,
        private Security $security,
        private RequestIdentifier $requestIdentifier,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return CommentResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];
        $threadId = $filters['thread'] ?? null;

        if ($threadId === null) {
            return [];
        }

        $comments = $this->commentRepository->findBy(['thread' => (int) $threadId]);

        $voteCacheIds = [];
        foreach ($comments as $comment) {
            $cacheId = $comment->voteCache?->id;
            if ($cacheId !== null) {
                $voteCacheIds[] = $cacheId;
            }
        }

        $userVotesByCacheId = $this->fetchUserVotes($voteCacheIds);

        return $this->commentBuilder->buildList($comments, $userVotesByCacheId);
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
        if ($request === null) {
            return [];
        }

        $identifier = $this->requestIdentifier->fromRequest($request);

        return $this->voteRepository->findValuesByIdentifierAndVoteCacheIds($identifier, $voteCacheIds);
    }
}
