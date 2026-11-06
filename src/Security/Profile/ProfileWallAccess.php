<?php

declare(strict_types=1);

namespace App\Security\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\User;
use App\Repository\Profile\ProfilePostRepository;
use App\Repository\User\Relation\UserBlockRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Who may see a member's wall (#1316): the rules of their profile, plus blocks, read the same by every door. */
readonly class ProfileWallAccess
{
    public function __construct(
        private UserRepository $userRepository,
        private PublicProfileGuard $publicProfileGuard,
        private UserBlockRepository $userBlockRepository,
        private ProfilePostRepository $profilePostRepository,
    ) {
    }

    /** The wall's owner, or the 404 their profile would give: gone, suspended or private (#1308). */
    public function owner(string $username): User
    {
        $owner = $this->userRepository->findOneBy(['username' => $username]);
        if (!$owner instanceof User || !$owner->isPubliclyVisible()) {
            throw new NotFoundHttpException('profile.not_found');
        }
        $this->publicProfileGuard->assertPublic($owner);

        return $owner;
    }

    /** A block either way hides the wall, without saying so: it reads as a wall with nothing on it. */
    public function isHiddenFrom(User $owner, ?User $viewer): bool
    {
        return $viewer instanceof User
            && $viewer->id !== $owner->id
            && $this->userBlockRepository->isBlockedEitherWay($owner, $viewer);
    }

    /** A post the viewer may read, or a 404 that does not say whether it exists: its wall's rules, read on its author. */
    public function visiblePost(string $postId, ?User $viewer): ProfilePost
    {
        $post = $this->profilePostRepository->find($postId);
        if (!$post instanceof ProfilePost || !$post->author->isPubliclyVisible()) {
            throw new NotFoundHttpException('profile.wall.post_not_found');
        }
        $this->publicProfileGuard->assertPublic($post->author);
        if ($this->isHiddenFrom($post->author, $viewer)) {
            throw new NotFoundHttpException('profile.wall.post_not_found');
        }

        return $post;
    }

    /**
     * The people whose comments and likes a viewer does not see: anyone blocked either way with them.
     *
     * @return list<string>
     */
    public function hiddenUserIdsFor(?User $viewer): array
    {
        return $viewer instanceof User ? array_map('strval', array_keys($this->userBlockRepository->findIdsBlockedEitherWay($viewer))) : [];
    }
}
