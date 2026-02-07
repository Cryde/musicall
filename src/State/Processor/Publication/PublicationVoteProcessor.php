<?php

declare(strict_types=1);

namespace App\State\Processor\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Publication\PublicationVoteInput;
use App\ApiResource\Publication\PublicationVoteSummary;
use App\Entity\Publication;
use App\Entity\User;
use App\Exception\PublicationNotFoundException;
use App\Repository\Metric\VoteRepository;
use App\Repository\PublicationRepository;
use App\Service\Identifier\RequestIdentifier;
use App\Service\Procedure\Metric\VoteProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<PublicationVoteInput, PublicationVoteSummary>
 */
readonly class PublicationVoteProcessor implements ProcessorInterface
{
    public function __construct(
        private PublicationRepository $publicationRepository,
        private VoteProcedure         $voteProcedure,
        private VoteRepository        $voteRepository,
        private Security              $security,
        private RequestIdentifier     $requestIdentifier,
        private RequestStack          $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublicationVoteSummary
    {
        $publication = $this->publicationRepository->findOneBy(['slug' => $uriVariables['slug'], 'status' => Publication::STATUS_ONLINE]);
        if (!$publication) {
            throw new PublicationNotFoundException('Publication inexistante');
        }
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        /** @var User|null $user */
        $user = $this->security->getUser();

        $userVote = $data->userVote;

        $this->voteProcedure->process($publication, $request, $userVote, $user);

        return $this->buildSummary($publication);
    }

    private function buildSummary(Publication $publication): PublicationVoteSummary
    {
        $voteCache = $publication->getVoteCache();

        $summary = new PublicationVoteSummary();
        $summary->slug = $publication->getSlug();
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
