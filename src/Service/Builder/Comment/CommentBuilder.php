<?php declare(strict_types=1);

namespace App\Service\Builder\Comment;

use App\ApiResource\Comment\CommentResource;
use App\Entity\Comment\Comment;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

readonly class CommentBuilder
{
    public function __construct(
        private HtmlSanitizerInterface $appOnlybrSanitizer,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

    /**
     * @param Comment[] $entities
     * @param array<int, int> $userVotesByCacheId  vote_cache_id => -1|1
     * @return CommentResource[]
     */
    public function buildList(array $entities, array $userVotesByCacheId = []): array
    {
        return array_map(
            fn(Comment $entity): CommentResource => $this->buildItem(
                $entity,
                $entity->voteCache !== null ? ($userVotesByCacheId[$entity->voteCache->id] ?? null) : null,
            ),
            $entities,
        );
    }

    public function buildItem(Comment $entity, ?int $userVote = null): CommentResource
    {
        $dto = new CommentResource();
        $dto->id = $entity->id;
        $dto->threadId = $entity->thread->id;
        $dto->author = [
            'id' => $entity->author->id,
            'username' => $entity->author->username,
            'profile_picture_url' => $this->profilePictureUrlBuilder->build($entity->author),
            'deletion_datetime' => $entity->author->deletionDatetime?->format(\DateTimeInterface::ATOM),
        ];
        $dto->content = $this->appOnlybrSanitizer->sanitize(nl2br($entity->content));
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->upvotes = $entity->voteCache->upvoteCount ?? 0;
        $dto->downvotes = $entity->voteCache->downvoteCount ?? 0;
        $dto->userVote = $userVote;

        return $dto;
    }
}
