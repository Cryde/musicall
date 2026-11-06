<?php

declare(strict_types=1);

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\ProfilePostLikeState;
use App\Entity\User;
use App\Repository\Profile\ProfilePostLikeRepository;
use App\Security\Profile\ProfileWallAccess;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<null, ProfilePostLikeState>
 */
readonly class ProfilePostLikePostProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProfileWallAccess $wallAccess,
        private ProfilePostLikeRepository $likeRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProfilePostLikeState
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $post = $this->wallAccess->visiblePost((string) $uriVariables['postId'], $user);
        $this->likeRepository->add($post, $user);

        $state = new ProfilePostLikeState();
        $state->postId = (string) $post->id;
        $hidden = $this->wallAccess->hiddenUserIdsFor($user);
        $state->likeCount = $this->likeRepository->countByPostIds([$state->postId], $hidden)[$state->postId] ?? 0;
        $state->hasLiked = true;

        return $state;
    }
}
