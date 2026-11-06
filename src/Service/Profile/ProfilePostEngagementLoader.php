<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentRepository;
use App\Repository\Profile\ProfilePostLikeRepository;
use App\Security\Profile\ProfileWallAccess;

/** The likes and comments of a page of posts, in a fixed number of queries whatever the page holds. */
readonly class ProfilePostEngagementLoader
{
    public function __construct(
        private ProfilePostLikeRepository $likeRepository,
        private ProfilePostCommentRepository $commentRepository,
        private ProfileWallAccess $wallAccess,
    ) {
    }

    /**
     * @param list<ProfilePost> $posts
     *
     * @return array<string, ProfilePostEngagement> by post id
     */
    public function forPosts(array $posts, ?User $viewer): array
    {
        $ids = array_map(static fn (ProfilePost $post): string => (string) $post->id, $posts);
        // Counted as the viewer reads the likers and the comments: someone blocked either way is not there for them.
        $hidden = $this->wallAccess->hiddenUserIdsFor($viewer);
        $likeCounts = $this->likeRepository->countByPostIds($ids, $hidden);
        $liked = $viewer instanceof User ? $this->likeRepository->findLikedPostIds($ids, $viewer) : [];
        $commentCounts = $this->commentRepository->countByPostIds($ids, $hidden);

        $engagements = [];
        foreach ($ids as $id) {
            $engagements[$id] = new ProfilePostEngagement(
                $likeCounts[$id] ?? 0,
                isset($liked[$id]),
                $commentCounts[$id] ?? 0,
            );
        }

        return $engagements;
    }
}
