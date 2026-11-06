<?php

declare(strict_types=1);

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\ProfilePostCommentCreate;
use App\ApiResource\Profile\ProfilePostCommentResource;
use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentRepository;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\Profile\ProfilePostCommentResourceBuilder;
use App\Service\Procedure\Profile\CreateProfilePostCommentProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<ProfilePostCommentCreate, ProfilePostCommentResource>
 */
readonly class ProfilePostCommentCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProfileWallAccess $wallAccess,
        private ProfilePostCommentRepository $commentRepository,
        private CreateProfilePostCommentProcedure $createProcedure,
        private ProfilePostCommentResourceBuilder $commentBuilder,
        #[Target('profile_post_comment')]
        private RateLimiterFactoryInterface $commentLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProfilePostCommentResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        // A wall hidden by a block takes no comment, both ways: it answers as a post that does not exist.
        $post = $this->wallAccess->visiblePost((string) $uriVariables['postId'], $user);
        $parent = $this->parent($data->parentId, $post, $user);
        $this->commentLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        return $this->commentBuilder->build($this->createProcedure->process($post, $user, $data->content, $parent), $user);
    }

    private function parent(?string $parentId, ProfilePost $post, User $user): ?ProfilePostComment
    {
        if ($parentId === null) {
            return null;
        }
        $parent = $this->commentRepository->findOneOfPost($parentId, $post);
        // A comment by someone blocked either way is not there for this member, so it cannot be answered.
        if (!$parent instanceof ProfilePostComment
            || in_array((string) $parent->author->id, $this->wallAccess->hiddenUserIdsFor($user), true)) {
            throw new NotFoundHttpException('profile.wall.comment_not_found');
        }
        if ($parent->parent instanceof ProfilePostComment) {
            throw new UnprocessableEntityHttpException('profile.wall.comment_reply_nested');
        }

        return $parent;
    }
}
