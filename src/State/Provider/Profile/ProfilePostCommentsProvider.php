<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\ProfilePostCommentResource;
use App\ApiResource\Profile\ProfilePostComments;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentRepository;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\Profile\ProfilePostCommentResourceBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<ProfilePostComments>
 */
readonly class ProfilePostCommentsProvider implements ProviderInterface
{
    public function __construct(
        private ProfileWallAccess $wallAccess,
        private ProfilePostCommentRepository $commentRepository,
        private ProfilePostCommentResourceBuilder $commentBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProfilePostComments
    {
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;
        $post = $this->wallAccess->visiblePost((string) $uriVariables['postId'], $viewer);

        $comments = new ProfilePostComments();
        $comments->postId = (string) $post->id;
        $comments->comments = array_map(
            fn (ProfilePostComment $comment): ProfilePostCommentResource => $this->commentBuilder->build($comment, $viewer),
            $this->commentRepository->findForPost($post, $this->wallAccess->hiddenUserIdsFor($viewer)),
        );

        return $comments;
    }
}
