<?php declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\TopicPost;
use App\Entity\Forum\ForumPost as ForumPostEntity;
use App\Entity\User;
use App\Repository\Metric\VoteRepository;
use App\Service\Identifier\RequestIdentifier;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class TopicPostListBuilder
{
    public function __construct(
        private UserDtoBuilder         $userDtoBuilder,
        private HtmlSanitizerInterface $appForumSanitizer,
        private VoteRepository         $voteRepository,
        private Security               $security,
        private RequestIdentifier      $requestIdentifier,
        private RequestStack           $requestStack,
    ) {
    }

    /**
     * @param ForumPostEntity[] $posts
     *
     * @return TopicPost[]
     */
    public function buildFromEntities(array $posts): array
    {
        return array_map(
            fn (ForumPostEntity $post): TopicPost => $this->buildFromEntity($post),
            $posts
        );
    }

    public function buildFromEntity(ForumPostEntity $post): TopicPost
    {
        $item = new TopicPost();
        $item->id = (string) $post->id;
        $item->creationDatetime = $post->creationDatetime;
        $item->updateDatetime = $post->updateDatetime;
        $item->content = $this->appForumSanitizer->sanitize(nl2br($post->content));
        $item->creator = $this->userDtoBuilder->buildFromEntity($post->creator);

        $voteCache = $post->voteCache;
        $item->upvotes = $voteCache->upvoteCount ?? 0;
        $item->downvotes = $voteCache->downvoteCount ?? 0;

        if ($voteCache) {
            /** @var User|null $currentUser */
            $currentUser = $this->security->getUser();
            if ($currentUser) {
                $vote = $this->voteRepository->findOneByUserAndVoteCache($currentUser, $voteCache);
                $item->userVote = $vote?->value;
            } else {
                $request = $this->requestStack->getCurrentRequest();
                if ($request) {
                    $identifier = $this->requestIdentifier->fromRequest($request);
                    $vote = $this->voteRepository->findOneByIdentifierAndVoteCache($identifier, $voteCache);
                    $item->userVote = $vote?->value;
                }
            }
        }

        return $item;
    }
}
