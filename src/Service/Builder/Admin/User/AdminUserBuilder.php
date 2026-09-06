<?php declare(strict_types=1);

namespace App\Service\Builder\Admin\User;

use App\ApiResource\Admin\User\AdminUser;
use App\ApiResource\Admin\User\AdminUserActivity;
use App\Entity\Comment\Comment;
use App\Entity\Forum\ForumPost;
use App\Entity\Image\UserProfilePicture;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Publication;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Comment\CommentRepository;
use App\Repository\Forum\ForumPostRepository;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Repository\PublicationRepository;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * Shared by the search list and the user page, so the two cannot disagree about who an account is.
 */
readonly class AdminUserBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private PublicationRepository $publicationRepository,
        private CommentRepository $commentRepository,
        private ForumPostRepository $forumPostRepository,
        private MusicianAnnounceRepository $musicianAnnounceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
    ) {
    }

    public function buildFromEntity(User $user): AdminUser
    {
        $resource = new AdminUser();
        $resource->id = $user->id;
        $resource->username = $user->username;
        $resource->email = $user->email;
        // The stored roles, not getRoles(): that one adds ROLE_USER to everyone, and showing a role
        // the roles screen cannot turn off would be a lie about what is written down.
        $resource->roles = array_values($user->roles);
        $resource->creationDatetime = $user->creationDatetime;
        $resource->lastLoginDatetime = $user->lastLoginDatetime;
        $resource->lastActivityDatetime = $user->lastActivityDatetime;
        $resource->confirmationDatetime = $user->confirmationDatetime;
        $resource->usernameChangedDatetime = $user->usernameChangedDatetime;
        $resource->deletionDatetime = $user->deletionDatetime;
        $resource->isDeleted = $user->isDeleted();
        $resource->isEmailConfirmed = $user->confirmationDatetime instanceof \DateTimeInterface;
        $resource->hasMusicianProfile = $user->musicianProfile !== null;
        $resource->hasTeacherProfile = $user->teacherProfile !== null;
        $resource->profilePictureUrl = $this->buildProfilePictureUrl($user);

        return $resource;
    }

    /**
     * @param list<User> $users
     *
     * @return list<AdminUser>
     */
    public function buildFromEntities(array $users): array
    {
        return array_map($this->buildFromEntity(...), $users);
    }

    /**
     * The user page only. Five counts per row would make the search page pay for a question it is
     * not asking.
     */
    public function buildWithActivity(User $user): AdminUser
    {
        $resource = $this->buildFromEntity($user);

        $activity = new AdminUserActivity();
        $activity->publications = $this->publicationRepository->count(['author' => $user]);
        $activity->comments = $this->commentRepository->count(['author' => $user]);
        $activity->forumPosts = $this->forumPostRepository->count(['creator' => $user]);
        $activity->musicianAnnounces = $this->musicianAnnounceRepository->count(['author' => $user]);
        $activity->bandSpaces = $this->bandSpaceMembershipRepository->count([
            'user' => $user,
            'status' => MembershipStatus::Active,
        ]);

        $resource->activity = $activity;

        return $resource;
    }

    private function buildProfilePictureUrl(User $user): ?string
    {
        $profilePicture = $user->profilePicture;
        if (!$profilePicture instanceof UserProfilePicture) {
            return null;
        }
        if (!$path = $this->uploaderHelper->asset($profilePicture, 'imageFile')) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
    }
}
