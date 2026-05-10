<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\ForumPostResource;
use App\Entity\Forum\ForumPost;
use App\Entity\Metric\VoteCache;
use App\Entity\User;
use App\Exception\ForumPostNotFoundException;
use App\Repository\Forum\ForumPostRepository;
use App\Repository\Metric\VoteRepository;
use App\Service\Builder\Forum\ForumPostBuilder;
use App\Service\Identifier\RequestIdentifier;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<ForumPostResource>
 */
readonly class ForumPostItemProvider implements ProviderInterface
{
    public function __construct(
        private ForumPostRepository $forumPostRepository,
        private VoteRepository      $voteRepository,
        private ForumPostBuilder    $forumPostBuilder,
        private Security            $security,
        private RequestIdentifier   $requestIdentifier,
        private RequestStack        $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ForumPostResource
    {
        $forumPost = $this->forumPostRepository->find($uriVariables['id']);
        if (!$forumPost instanceof ForumPost) {
            throw new ForumPostNotFoundException('Message de forum inexistant');
        }

        $userVote = $this->resolveUserVote($forumPost->voteCache);

        return $this->forumPostBuilder->buildItem($forumPost, $userVote);
    }

    private function resolveUserVote(?VoteCache $voteCache): ?int
    {
        if (!$voteCache instanceof VoteCache) {
            return null;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if ($user) {
            return $this->voteRepository->findOneByUserAndVoteCache($user, $voteCache)?->value;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return null;
        }

        $identifier = $this->requestIdentifier->fromRequest($request);

        return $this->voteRepository->findOneByIdentifierAndVoteCache($identifier, $voteCache)?->value;
    }
}
