<?php

declare(strict_types=1);

namespace App\Service\Procedure\Profile;

use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentLikeRepository;
use App\Repository\Profile\ProfilePostCommentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Liking a comment and taking the like back (#1331): the like and the comment's stored count move in one
 * transaction, and only when the like itself did, so a double tap counts once.
 */
readonly class ProfilePostCommentLikeProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProfilePostCommentLikeRepository $likeRepository,
        private ProfilePostCommentRepository $commentRepository,
    ) {
    }

    public function like(ProfilePostComment $comment, User $user): void
    {
        $this->entityManager->wrapInTransaction(function () use ($comment, $user): void {
            if ($this->likeRepository->add($comment, $user)) {
                $this->commentRepository->incrementLikeCount($comment);
            }
        });
    }

    /**
     * @return bool whether a like was there to take back
     */
    public function unlike(ProfilePostComment $comment, User $user): bool
    {
        return $this->entityManager->wrapInTransaction(function () use ($comment, $user): bool {
            if (!$this->likeRepository->remove($comment, $user)) {
                return false;
            }
            $this->commentRepository->decrementLikeCount($comment);

            return true;
        });
    }
}
