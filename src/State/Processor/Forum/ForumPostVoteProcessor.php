<?php

declare(strict_types=1);

namespace App\State\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Forum\ForumPostVoteInput;
use App\ApiResource\Forum\ForumPostVoteSummary;
use App\Entity\Forum\ForumPost;
use App\Entity\User;
use App\Exception\ForumPostNotFoundException;
use App\Repository\Forum\ForumPostRepository;
use App\Repository\Metric\VoteRepository;
use App\Service\Identifier\RequestIdentifier;
use App\Service\Procedure\Metric\VoteProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<ForumPostVoteInput, ForumPostVoteSummary>
 */
readonly class ForumPostVoteProcessor implements ProcessorInterface
{
    public function __construct(
        private ForumPostRepository $forumPostRepository,
        private VoteProcedure       $voteProcedure,
        private VoteRepository      $voteRepository,
        private Security            $security,
        private RequestIdentifier   $requestIdentifier,
        private RequestStack        $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ForumPostVoteSummary
    {
        $forumPost = $this->forumPostRepository->find($uriVariables['id']);
        if (!$forumPost instanceof \App\Entity\Forum\ForumPost) {
            throw new ForumPostNotFoundException('Message de forum inexistant');
        }
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        /** @var User|null $user */
        $user = $this->security->getUser();

        $userVote = $data->userVote;

        $this->voteProcedure->process($forumPost, $request, $userVote, $user);

        return $this->buildSummary($forumPost);
    }

    private function buildSummary(ForumPost $forumPost): ForumPostVoteSummary
    {
        $voteCache = $forumPost->voteCache;

        $summary = new ForumPostVoteSummary();
        $summary->id = (string) $forumPost->id;
        $summary->upvotes = $voteCache->upvoteCount ?? 0;
        $summary->downvotes = $voteCache->downvoteCount ?? 0;

        if ($voteCache instanceof \App\Entity\Metric\VoteCache) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            if ($user) {
                $vote = $this->voteRepository->findOneByUserAndVoteCache($user, $voteCache);
                $summary->userVote = $vote?->value;
            } else {
                $request = $this->requestStack->getCurrentRequest();
                if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
                    $identifier = $this->requestIdentifier->fromRequest($request);
                    $vote = $this->voteRepository->findOneByIdentifierAndVoteCache($identifier, $voteCache);
                    $summary->userVote = $vote?->value;
                }
            }
        }

        return $summary;
    }
}
