<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\ProfilePostCommentResource;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\Profile\ProfilePostCommentResourceBuilder;
use App\Service\Profile\Wall\CommentCursor;
use App\Service\Profile\Wall\CommentOrder;
use App\Service\Profile\Wall\CommentThread;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<ProfilePostCommentResource>
 */
readonly class ProfilePostCommentsProvider implements ProviderInterface
{
    public function __construct(
        private ProfileWallAccess $wallAccess,
        private CommentThread $thread,
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

        // The operation's constraints have already refused a malformed cursor or comment id with a 422.
        $beforeValue = $context['filters']['before'] ?? null;
        $before = is_string($beforeValue) && $beforeValue !== '' ? CommentCursor::parse($beforeValue) : null;
        $aroundValue = $context['filters']['around'] ?? null;
        $around = is_string($aroundValue) && $aroundValue !== '' ? $aroundValue : null;
        $comments = $this->thread->page($post, $this->wallAccess->hiddenUserIdsFor($viewer), $before, $around);

        return array_map(function (ProfilePostComment $comment) use ($viewer): ProfilePostCommentResource {
            $resource = $this->commentBuilder->build($comment, $viewer);
            if (!$comment->parent instanceof ProfilePostComment) {
                $resource->cursor = CommentCursor::of(CommentOrder::Recent, $comment)->toString();
            }

            return $resource;
        }, $comments);
    }
}
