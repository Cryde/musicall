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

/**
 * @implements ProviderInterface<ProfilePostCommentResource>
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

    /**
     * @return list<ProfilePostCommentResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;
        $post = $this->wallAccess->visiblePost((string) $uriVariables['postId'], $viewer);

        return array_map(
            fn (ProfilePostComment $comment): ProfilePostCommentResource => $this->commentBuilder->build($comment, $viewer),
            $this->commentRepository->findForPost($post, $this->wallAccess->hiddenUserIdsFor($viewer)),
        );
    }
}
