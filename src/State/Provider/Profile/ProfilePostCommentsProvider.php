<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\Profile\ProfilePostCommentResource;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\Profile\ProfilePostCommentResourceBuilder;
use App\Service\Profile\Wall\CommentCursor;
use App\Service\Profile\Wall\CommentOrder;
use App\Service\Profile\Wall\CommentThread;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProviderInterface<ProfilePostCommentResource>
 */
readonly class ProfilePostCommentsProvider implements ProviderInterface
{
    public const string CURSOR_OTHER_ORDER_ERROR_CODE = 'music_all_a0ad2e75-8064-463d-b45e-9b31d8703813';

    public function __construct(
        private ProfileWallAccess $wallAccess,
        private CommentThread $thread,
        private ProfilePostCommentResourceBuilder $commentBuilder,
        private TranslatorInterface $translator,
        private Security $security,
    ) {
    }

    /**
     * @return list<ProfilePostCommentResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // The operation's constraints have already refused a malformed order, cursor or comment id with a 422.
        $orderValue = $context['filters']['order'] ?? null;
        $order = is_string($orderValue) ? CommentOrder::tryFrom($orderValue) ?? CommentOrder::Recent : CommentOrder::Recent;
        $beforeValue = $context['filters']['before'] ?? null;
        $before = is_string($beforeValue) && $beforeValue !== '' ? CommentCursor::parse($beforeValue) : null;
        if ($before instanceof CommentCursor && $before->order !== $order) {
            $this->refuseCursorOfAnotherOrder($beforeValue);
        }
        $aroundValue = $context['filters']['around'] ?? null;
        $around = is_string($aroundValue) && $aroundValue !== '' ? $aroundValue : null;

        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;
        $post = $this->wallAccess->visiblePost((string) $uriVariables['postId'], $viewer);
        $comments = $this->thread->page($post, $this->wallAccess->hiddenUserIdsFor($viewer), $order, $before, $around);

        return array_map(function (ProfilePostComment $comment) use ($viewer, $order): ProfilePostCommentResource {
            $resource = $this->commentBuilder->build($comment, $viewer);
            if (!$comment->parent instanceof ProfilePostComment) {
                $resource->cursor = CommentCursor::of($order, $comment)->toString();
            }

            return $resource;
        }, $comments);
    }

    /**
     * Each order pages its own way, so a cursor goes on only in the order it was made for. A constraint cannot
     * say so, as it reads one parameter: the same 422 is built by hand, naming `before`.
     */
    private function refuseCursorOfAnotherOrder(string $cursor): never
    {
        $messageKey = 'profile.wall.comments_cursor_other_order';
        throw new ValidationException(new ConstraintViolationList([
            new ConstraintViolation(
                $this->translator->trans($messageKey, [], 'validators'),
                $messageKey,
                [],
                null,
                'before',
                $cursor,
                code: self::CURSOR_OTHER_ORDER_ERROR_CODE,
            ),
        ]));
    }
}
