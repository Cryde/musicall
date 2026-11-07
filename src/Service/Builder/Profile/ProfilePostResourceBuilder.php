<?php

declare(strict_types=1);

namespace App\Service\Builder\Profile;

use App\ApiResource\Profile\ProfilePostAuthor;
use App\ApiResource\Profile\ProfilePostMedia;
use App\ApiResource\Profile\ProfilePostPhoto;
use App\ApiResource\Profile\ProfilePostResource;
use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostImage;
use App\Entity\User;
use App\Enum\Musician\MediaPlatform;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use App\Service\Profile\ProfilePostEngagement;
use App\Service\Profile\ProfilePostEngagementLoader;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class ProfilePostResourceBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
        private UrlGeneratorInterface $urlGenerator,
        private ProfilePostEngagementLoader $engagementLoader,
    ) {
    }

    public function build(ProfilePost $post, ?User $viewer): ProfilePostResource
    {
        return $this->buildAll([$post], $viewer)[0];
    }

    /**
     * @param list<ProfilePost> $posts
     *
     * @return list<ProfilePostResource>
     */
    public function buildAll(array $posts, ?User $viewer): array
    {
        $engagements = $this->engagementLoader->forPosts($posts, $viewer);

        return array_map(
            fn (ProfilePost $post): ProfilePostResource => $this->resource($post, $viewer, $engagements[(string) $post->id]),
            $posts,
        );
    }

    private function resource(ProfilePost $post, ?User $viewer, ProfilePostEngagement $engagement): ProfilePostResource
    {
        $author = $post->author;

        $resource = new ProfilePostResource();
        $resource->id = (string) $post->id;
        $resource->author = new ProfilePostAuthor();
        $resource->author->username = $author->username;
        $resource->author->displayName = $author->profile->displayName ?: $author->username;
        $resource->author->profilePictureUrl = $this->profilePictureUrlBuilder->build($author);
        $resource->content = $post->content;
        $resource->photos = array_values(array_map(
            fn (ProfilePostImage $image): ProfilePostPhoto => $this->photo($post, $image),
            $post->images->toArray(),
        ));
        $resource->media = $this->media($post);
        $resource->isPinned = $post->isPinned;
        $resource->creationDatetime = $post->creationDatetime;
        $resource->updateDatetime = $post->updateDatetime;
        $resource->canEdit = $viewer instanceof User && $viewer->id === $author->id;
        $resource->likeCount = $engagement->likeCount;
        $resource->hasLiked = $engagement->hasLiked;
        $resource->commentCount = $engagement->commentCount;

        return $resource;
    }

    private function photo(ProfilePost $post, ProfilePostImage $image): ProfilePostPhoto
    {
        $photo = new ProfilePostPhoto();
        $photo->id = (string) $image->id;
        $photo->url = $this->urlGenerator->generate('api_profile_post_photos_get', [
            'postId' => $post->id,
            'id' => $image->id,
        ]);

        return $photo;
    }

    private function media(ProfilePost $post): ?ProfilePostMedia
    {
        if (!$post->mediaPlatform instanceof MediaPlatform || $post->mediaEmbedId === null || $post->mediaUrl === null) {
            return null;
        }
        $media = new ProfilePostMedia();
        $media->platform = $post->mediaPlatform->value;
        $media->embedId = $post->mediaEmbedId;
        $media->url = $post->mediaUrl;
        $media->title = $post->mediaTitle;

        return $media;
    }
}
