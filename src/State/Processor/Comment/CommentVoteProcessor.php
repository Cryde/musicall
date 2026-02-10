<?php

declare(strict_types=1);

namespace App\State\Processor\Comment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Comment\CommentVoteInput;
use App\ApiResource\Comment\CommentVoteSummary;
use App\Entity\Comment\Comment;
use App\Entity\User;
use App\Exception\CommentNotFoundException;
use App\Repository\Comment\CommentRepository;
use App\Repository\Metric\VoteRepository;
use App\Service\Identifier\RequestIdentifier;
use App\Service\Procedure\Metric\VoteProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<CommentVoteInput, CommentVoteSummary>
 */
readonly class CommentVoteProcessor implements ProcessorInterface
{
    public function __construct(
        private CommentRepository $commentRepository,
        private VoteProcedure     $voteProcedure,
        private VoteRepository    $voteRepository,
        private Security          $security,
        private RequestIdentifier $requestIdentifier,
        private RequestStack      $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CommentVoteSummary
    {
        $comment = $this->commentRepository->find($uriVariables['id']);
        if (!$comment) {
            throw new CommentNotFoundException('Commentaire inexistant');
        }
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        /** @var User|null $user */
        $user = $this->security->getUser();

        $userVote = $data->userVote;

        $this->voteProcedure->process($comment, $request, $userVote, $user);

        return $this->buildSummary($comment);
    }

    private function buildSummary(Comment $comment): CommentVoteSummary
    {
        $voteCache = $comment->getVoteCache();

        $summary = new CommentVoteSummary();
        $summary->id = $comment->getId();
        $summary->upvotes = $voteCache?->getUpvoteCount() ?? 0;
        $summary->downvotes = $voteCache?->getDownvoteCount() ?? 0;

        if ($voteCache) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            if ($user) {
                $vote = $this->voteRepository->findOneByUserAndVoteCache($user, $voteCache);
                $summary->userVote = $vote?->getValue();
            } else {
                $request = $this->requestStack->getCurrentRequest();
                if ($request) {
                    $identifier = $this->requestIdentifier->fromRequest($request);
                    $vote = $this->voteRepository->findOneByIdentifierAndVoteCache($identifier, $voteCache);
                    $summary->userVote = $vote?->getValue();
                }
            }
        }

        return $summary;
    }
}
