<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\ProfilePostResource;
use App\Entity\Profile\ProfilePost;
use App\Entity\User;
use App\Repository\Profile\ProfilePostRepository;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\Profile\ProfilePostResourceBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<ProfilePostResource>
 */
readonly class ProfilePinnedPostsProvider implements ProviderInterface
{
    public function __construct(
        private ProfileWallAccess $wallAccess,
        private ProfilePostRepository $profilePostRepository,
        private ProfilePostResourceBuilder $postBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return list<ProfilePostResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $owner = $this->wallAccess->owner((string) $uriVariables['username']);
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;
        if ($this->wallAccess->isHiddenFrom($owner, $viewer)) {
            return [];
        }

        $pinned = $this->profilePostRepository->findPinned($owner);

        return $pinned instanceof ProfilePost ? [$this->postBuilder->build($pinned, $viewer)] : [];
    }
}
