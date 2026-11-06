<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\ProfilePostResource;
use App\Entity\Profile\ProfilePost;
use App\Entity\User;
use App\Repository\Profile\ProfilePostRepository;
use App\Service\Builder\Profile\ProfilePostResourceBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Your own post, for its edit or its removal. Someone else's answers as a post that does not exist.
 *
 * @implements ProviderInterface<ProfilePostResource>
 */
readonly class ProfilePostEditProvider implements ProviderInterface
{
    public function __construct(
        private ProfilePostRepository $profilePostRepository,
        private ProfilePostResourceBuilder $postBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProfilePostResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $post = $this->profilePostRepository->findOneOfAuthor((string) $uriVariables['id'], $user);
        if (!$post instanceof ProfilePost) {
            throw new NotFoundHttpException('profile.wall.post_not_found');
        }

        return $this->postBuilder->build($post, $user);
    }
}
