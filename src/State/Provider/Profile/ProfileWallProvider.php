<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\ProfileWall;
use App\Entity\Profile\ProfilePost;
use App\Entity\User;
use App\Repository\Profile\ProfilePostRepository;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\Profile\ProfilePostResourceBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ProfileWall>
 */
readonly class ProfileWallProvider implements ProviderInterface
{
    public function __construct(
        private ProfileWallAccess $wallAccess,
        private ProfilePostRepository $profilePostRepository,
        private ProfilePostResourceBuilder $postBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProfileWall
    {
        $owner = $this->wallAccess->owner((string) $uriVariables['username']);
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;

        $wall = new ProfileWall();
        $wall->username = $owner->username;
        if ($this->wallAccess->isHiddenFrom($owner, $viewer)) {
            return $wall;
        }

        // Already a uuid when present: the operation's Assert\Uuid refused anything else with a 422.
        $beforeId = $context['filters']['before'] ?? null;
        $before = null;
        $pinned = null;
        if (is_string($beforeId) && $beforeId !== '') {
            $before = $this->profilePostRepository->findOneOfAuthor($beforeId, $owner);
            if (!$before instanceof ProfilePost) {
                throw new NotFoundHttpException('profile.wall.post_not_found');
            }
        } else {
            $pinned = $this->profilePostRepository->findPinned($owner);
        }

        $posts = $this->profilePostRepository->findWallPage($owner, $before, ProfileWall::PAGE_SIZE + 1);
        $wall->hasMore = count($posts) > ProfileWall::PAGE_SIZE;
        $posts = array_slice($posts, 0, ProfileWall::PAGE_SIZE);
        // Built together, so the likes and comments of the whole page come in one go.
        $built = $this->postBuilder->buildAll($pinned instanceof ProfilePost ? [$pinned, ...$posts] : $posts, $viewer);
        $wall->pinnedPost = $pinned instanceof ProfilePost ? array_shift($built) : null;
        $wall->posts = $built;

        return $wall;
    }
}
