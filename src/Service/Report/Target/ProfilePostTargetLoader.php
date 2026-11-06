<?php

declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\Profile\ProfilePost;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\Profile\ProfilePostRepository;
use App\Repository\User\Relation\UserBlockRepository;
use App\Service\Report\ReportTarget;

/** A post on a member's wall (#1316): reportable only by who can read it. */
readonly class ProfilePostTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(
        private ProfilePostRepository $profilePostRepository,
        private UserBlockRepository $userBlockRepository,
    ) {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::ProfilePost;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        $post = $this->profilePostRepository->find($id);
        if (
            !$post instanceof ProfilePost
            || !$post->author->isPubliclyVisible()
            || !$post->author->profile->isPublic
            || $this->userBlockRepository->isBlockedEitherWay($post->author, $reporter)
        ) {
            return null;
        }

        return $this->target($post);
    }

    public function current(string $id): ?ReportTarget
    {
        $post = $this->profilePostRepository->find($id);
        if (!$post instanceof ProfilePost || $post->author->isDeleted()) {
            return null;
        }

        return $this->target($post);
    }

    private function target(ProfilePost $post): ReportTarget
    {
        return new ReportTarget(
            $post->author,
            ReportTarget::text((string) $post->content, (string) $post->mediaUrl),
            ['username' => $post->author->username, 'photo_count' => $post->images->count()],
        );
    }
}
