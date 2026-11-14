<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostComment;
use App\Repository\Profile\ProfilePostCommentRepository;
use App\Security\Profile\ProfileWallAccess;

/**
 * A page of a post's comments (#1331): top-level comments in the order asked for, each followed by all its replies,
 * so a reply never arrives without the comment it answers.
 */
readonly class CommentThread
{
    public const int PAGE_SIZE = 20;

    /** How many top-level comments a page reaching down to a comment may hold, past which the first page opens. */
    public const int AROUND_LIMIT = 100;

    public function __construct(
        private ProfilePostCommentRepository $commentRepository,
        private ProfileWallAccess $wallAccess,
    ) {
    }

    /**
     * `$around` names a comment a notification links to: the first page in `$order` then reaches down to it, so the
     * usual `before` goes on from there. It only shapes a first page, and falls back to the plain one for a comment
     * not found, hidden from the viewer, already on it, or too far down.
     *
     * @param list<string> $hiddenUserIds
     *
     * @return list<ProfilePostComment>
     */
    public function page(ProfilePost $post, array $hiddenUserIds, CommentOrder $order, ?CommentCursor $before, ?string $around): array
    {
        $rootIds = $before === null && $around !== null ? $this->rootIdsReaching($post, $hiddenUserIds, $order, $around) : null;
        $rootIds ??= $this->commentRepository->findRootIdsPage($post, $hiddenUserIds, $order, $before, self::PAGE_SIZE);

        return $this->commentRepository->findWithReplies($rootIds, $hiddenUserIds);
    }

    /**
     * @param list<string> $hiddenUserIds
     *
     * @return list<string>|null null when the first page will do
     */
    private function rootIdsReaching(ProfilePost $post, array $hiddenUserIds, CommentOrder $order, string $commentId): ?array
    {
        $target = $this->commentRepository->findOneOfPost($commentId, $post);
        if (!$target instanceof ProfilePostComment || $this->wallAccess->hidesComment($target, $hiddenUserIds)) {
            return null;
        }
        $root = $target->parent ?? $target;
        $rootIds = $this->commentRepository->findRootIdsDownTo($post, $hiddenUserIds, CommentCursor::of($order, $root), self::AROUND_LIMIT + 1);

        // Within the first page, that page holds it; a longer one stays full, so the client still knows more follow.
        return count($rootIds) > self::PAGE_SIZE && count($rootIds) <= self::AROUND_LIMIT ? $rootIds : null;
    }
}
