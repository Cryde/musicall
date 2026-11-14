<?php

declare(strict_types=1);

namespace App\Service\Builder\Profile;

use App\ApiResource\Profile\ProfilePostCommentAuthor;
use App\ApiResource\Profile\ProfilePostCommentResource;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentLikeRepository;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use App\Translation\DisplayNameTranslator;

readonly class ProfilePostCommentResourceBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
        private DisplayNameTranslator $displayNames,
        private ProfilePostCommentLikeRepository $likeRepository,
        private ProfileWallAccess $wallAccess,
    ) {
    }

    public function build(ProfilePostComment $comment, ?User $viewer): ProfilePostCommentResource
    {
        return $this->buildAll([$comment], $viewer, $this->wallAccess->hiddenUserIdsFor($viewer))[0];
    }

    /**
     * Their likes in two queries whatever the page holds (#1331), counted as the viewer reads them: without anyone
     * blocked either way.
     *
     * @param list<ProfilePostComment> $comments
     * @param list<string>             $hiddenUserIds from {@see ProfileWallAccess::hiddenUserIdsFor()}
     *
     * @return list<ProfilePostCommentResource> in the order given
     */
    public function buildAll(array $comments, ?User $viewer, array $hiddenUserIds): array
    {
        $ids = array_map(static fn (ProfilePostComment $comment): string => (string) $comment->id, $comments);
        $likeCounts = $this->likeRepository->countByCommentIds($ids, $hiddenUserIds);
        $liked = $viewer instanceof User ? $this->likeRepository->findLikedCommentIds($ids, $viewer) : [];

        return array_map(function (ProfilePostComment $comment) use ($viewer, $likeCounts, $liked): ProfilePostCommentResource {
            $resource = $this->resource($comment, $viewer);
            $resource->likeCount = $likeCounts[(string) $comment->id] ?? 0;
            $resource->hasLiked = isset($liked[(string) $comment->id]);

            return $resource;
        }, $comments);
    }

    private function resource(ProfilePostComment $comment, ?User $viewer): ProfilePostCommentResource
    {
        $resource = new ProfilePostCommentResource();
        $resource->id = (string) $comment->id;
        $resource->parentId = $comment->parent?->id;
        $resource->author = new ProfilePostCommentAuthor();
        $resource->author->username = $comment->author->username;
        $resource->author->displayName = $this->displayNames->publicName($comment->author);
        $resource->author->profilePictureUrl = $this->profilePictureUrlBuilder->build($comment->author);
        $resource->author->deletionDatetime = $comment->author->deletionDatetime;
        $resource->content = $comment->content;
        $resource->creationDatetime = $comment->creationDatetime;
        // The wall's owner keeps their wall: they may remove what anyone wrote on it.
        $resource->canDelete = $viewer instanceof User
            && ($viewer->id === $comment->author->id || $viewer->id === $comment->post->author->id);

        return $resource;
    }
}
