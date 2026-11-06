<?php

declare(strict_types=1);

namespace App\Service\Builder\Profile;

use App\ApiResource\Profile\ProfilePostCommentAuthor;
use App\ApiResource\Profile\ProfilePostCommentResource;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use App\Translation\DisplayNameTranslator;

readonly class ProfilePostCommentResourceBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
        private DisplayNameTranslator $displayNames,
    ) {
    }

    public function build(ProfilePostComment $comment, ?User $viewer): ProfilePostCommentResource
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
