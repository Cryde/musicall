<?php declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\ForumPostResource;
use App\Entity\Forum\ForumPost;
use App\Entity\Metric\VoteCache;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

readonly class ForumPostBuilder
{
    public function __construct(
        private UserDtoBuilder         $userDtoBuilder,
        private HtmlSanitizerInterface $appForumSanitizer,
    ) {
    }

    /**
     * @param ForumPost[]     $entities
     * @param array<int, int> $userVotesByCacheId vote_cache_id => -1|1
     *
     * @return ForumPostResource[]
     */
    public function buildList(array $entities, array $userVotesByCacheId = []): array
    {
        return array_map(
            fn (ForumPost $entity): ForumPostResource => $this->buildItem(
                $entity,
                $entity->voteCache instanceof VoteCache ? ($userVotesByCacheId[$entity->voteCache->id] ?? null) : null,
            ),
            $entities,
        );
    }

    public function buildItem(ForumPost $entity, ?int $userVote = null): ForumPostResource
    {
        $dto = new ForumPostResource();
        $dto->id = (string) $entity->id;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->updateDatetime = $entity->updateDatetime;
        $dto->content = $this->appForumSanitizer->sanitize(nl2br($entity->content));
        $dto->creator = $this->userDtoBuilder->buildFromEntity($entity->creator);

        $voteCache = $entity->voteCache;
        $dto->upvotes = $voteCache->upvoteCount ?? 0;
        $dto->downvotes = $voteCache->downvoteCount ?? 0;
        $dto->userVote = $userVote;

        return $dto;
    }
}
