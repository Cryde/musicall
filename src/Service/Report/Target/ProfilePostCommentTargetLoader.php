<?php

declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\Profile\ProfilePostCommentRepository;
use App\Repository\User\Relation\UserBlockRepository;
use App\Service\Report\ReportTarget;

/**
 * A comment on a wall post (#1316), reportable only by who can read it: the wall open to them, and no
 * block either way with whoever wrote it. Named by the wall's owner.
 */
readonly class ProfilePostCommentTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(
        private ProfilePostCommentRepository $commentRepository,
        private UserBlockRepository $userBlockRepository,
    ) {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::ProfilePostComment;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        $comment = $this->commentRepository->find($id);
        if (!$comment instanceof ProfilePostComment) {
            return null;
        }
        $owner = $comment->post->author;
        if (!$owner->isPubliclyVisible()
            || !$owner->profile->isPublic
            || $this->userBlockRepository->isBlockedEitherWay($owner, $reporter)
            || $this->userBlockRepository->isBlockedEitherWay($comment->author, $reporter)) {
            return null;
        }

        return $this->target($comment);
    }

    public function current(string $id): ?ReportTarget
    {
        $comment = $this->commentRepository->find($id);

        return $comment instanceof ProfilePostComment ? $this->target($comment) : null;
    }

    private function target(ProfilePostComment $comment): ReportTarget
    {
        return new ReportTarget(
            $comment->author,
            ReportTarget::text($comment->content),
            ['username' => $comment->post->author->username],
        );
    }
}
