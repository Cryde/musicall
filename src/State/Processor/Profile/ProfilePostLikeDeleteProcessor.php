<?php

declare(strict_types=1);

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\Profile\ProfilePostLikeRepository;
use App\Security\Profile\ProfileWallAccess;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class ProfilePostLikeDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProfileWallAccess $wallAccess,
        private ProfilePostLikeRepository $likeRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $post = $this->wallAccess->visiblePost((string) $uriVariables['postId'], $user);
        if (!$this->likeRepository->remove($post, $user)) {
            throw new NotFoundHttpException('profile.wall.like_not_found');
        }

        return null;
    }
}
