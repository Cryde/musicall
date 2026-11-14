<?php

declare(strict_types=1);

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\ProfilePostCommentLikeState;
use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentLikeRepository;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Procedure\Profile\ProfilePostCommentLikeProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<null, ProfilePostCommentLikeState>
 */
readonly class ProfilePostCommentLikePostProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProfileWallAccess $wallAccess,
        private ProfilePostCommentLikeProcedure $likeProcedure,
        private ProfilePostCommentLikeRepository $likeRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProfilePostCommentLikeState
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $comment = $this->wallAccess->visibleComment((string) $uriVariables['commentId'], $user);
        $this->likeProcedure->like($comment, $user);

        $state = new ProfilePostCommentLikeState();
        $state->commentId = (string) $comment->id;
        $hidden = $this->wallAccess->hiddenUserIdsFor($user);
        $state->likeCount = $this->likeRepository->countByCommentIds([$state->commentId], $hidden)[$state->commentId] ?? 0;
        $state->hasLiked = true;

        return $state;
    }
}
