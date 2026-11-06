<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\ProfilePostCommentResource;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentRepository;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\Profile\ProfilePostCommentResourceBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One comment, read as its post's comment list would show it.
 *
 * @implements ProviderInterface<ProfilePostCommentResource>
 */
readonly class ProfilePostCommentProvider implements ProviderInterface
{
    public function __construct(
        private ProfileWallAccess $wallAccess,
        private ProfilePostCommentRepository $commentRepository,
        private ProfilePostCommentResourceBuilder $commentBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProfilePostCommentResource
    {
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;
        $comment = $this->commentRepository->find((string) $uriVariables['id']);
        if (!$comment instanceof ProfilePostComment) {
            throw new NotFoundHttpException('profile.wall.comment_not_found');
        }
        $this->wallAccess->visiblePost((string) $comment->post->id, $viewer);
        // Hidden as the list hides it: written by someone blocked either way, or answering them.
        $hidden = $this->wallAccess->hiddenUserIdsFor($viewer);
        if (in_array((string) $comment->author->id, $hidden, true)
            || ($comment->parent instanceof ProfilePostComment && in_array((string) $comment->parent->author->id, $hidden, true))) {
            throw new NotFoundHttpException('profile.wall.comment_not_found');
        }

        return $this->commentBuilder->build($comment, $viewer);
    }
}
