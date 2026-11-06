<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\ProfilePostAuthor;
use App\ApiResource\Profile\ProfilePostLikes;
use App\Entity\User;
use App\Repository\Profile\ProfilePostLikeRepository;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use App\Translation\DisplayNameTranslator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<ProfilePostLikes>
 */
readonly class ProfilePostLikesProvider implements ProviderInterface
{
    public function __construct(
        private ProfileWallAccess $wallAccess,
        private ProfilePostLikeRepository $likeRepository,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
        private DisplayNameTranslator $displayNames,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProfilePostLikes
    {
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;
        $post = $this->wallAccess->visiblePost((string) $uriVariables['postId'], $viewer);

        $likes = new ProfilePostLikes();
        $likes->postId = (string) $post->id;
        $likes->likers = array_map(
            $this->liker(...),
            $this->likeRepository->findLikers($post, $this->wallAccess->hiddenUserIdsFor($viewer)),
        );

        return $likes;
    }

    /** Named as anyone else reads them: a private profile shows its username. */
    private function liker(User $user): ProfilePostAuthor
    {
        $liker = new ProfilePostAuthor();
        $liker->username = $user->username;
        $liker->displayName = $this->displayNames->publicName($user);
        $liker->profilePictureUrl = $this->profilePictureUrlBuilder->build($user);

        return $liker;
    }
}
