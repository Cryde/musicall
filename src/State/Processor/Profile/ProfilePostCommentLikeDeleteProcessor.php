<?php

declare(strict_types=1);

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Procedure\Profile\ProfilePostCommentLikeProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class ProfilePostCommentLikeDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProfileWallAccess $wallAccess,
        private ProfilePostCommentLikeProcedure $likeProcedure,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $comment = $this->wallAccess->visibleComment((string) $uriVariables['commentId'], $user);
        if (!$this->likeProcedure->unlike($comment, $user)) {
            throw new NotFoundHttpException('profile.wall.comment_like_not_found');
        }

        return null;
    }
}
