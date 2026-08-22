<?php declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\ForumPostResource;
use App\Entity\Forum\ForumPost;
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
            function (ForumPost $entity) use ($userVotesByCacheId): ForumPostResource {
                // Read into a variable so the null is ruled out before the lookup: a vote cache id is
                // nullable until the row is persisted, and null is not a key this int-keyed map can
                // hold. PHP would quietly cast it to "" and never match.
                $cacheId = $entity->voteCache?->id;

                return $this->buildItem(
                    $entity,
                    $cacheId === null ? null : ($userVotesByCacheId[$cacheId] ?? null),
                );
            },
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
