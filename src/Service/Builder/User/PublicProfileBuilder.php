<?php

declare(strict_types=1);

namespace App\Service\Builder\User;

use App\ApiResource\User\Profile\PublicProfile;
use App\ApiResource\User\Profile\PublicProfileAnnounce;
use App\ApiResource\User\Profile\PublicProfileSocialLink;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Entity\User\UserProfile;
use App\Entity\User\UserSocialLink;
use App\Repository\Musician\MusicianAnnounceRepository;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class PublicProfileBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private MusicianAnnounceRepository $musicianAnnounceRepository,
    ) {
    }

    public function build(User $user): PublicProfile
    {
        $profile = $user->profile;
        $memberSince = $user->creationDatetime;

        $dto = new PublicProfile();

        $dto->username = $user->username;
        $dto->userId = (string) $user->id;
        $dto->displayName = $profile->displayName;
        $dto->bio = $profile->bio;
        $dto->location = $profile->location;
        $dto->memberSince = $memberSince;

        // Profile picture
        if ($user->profilePicture) {
            $path = $this->uploaderHelper->asset($user->profilePicture, 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
                $dto->profilePictureLargeUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_large');
            }
        }

        // Cover picture
        if ($profile->coverPicture) {
            $path = $this->uploaderHelper->asset($profile->coverPicture, 'imageFile');
            if ($path !== null) {
                $dto->coverPictureUrl = $this->cacheManager->getBrowserPath($path, 'user_cover_picture');
            }
        }

        // Social links
        $dto->socialLinks = $this->buildSocialLinks($profile->socialLinks->toArray());

        // Musician announces
        $announces = $this->musicianAnnounceRepository->findBy(['author' => $user], ['creationDatetime' => 'DESC']);
        $dto->musicianAnnounces = $this->buildAnnounces($announces);

        // Musician profile flag
        $dto->hasMusicianProfile = $user->musicianProfile !== null;

        // Teacher profile flag
        $dto->hasTeacherProfile = $user->teacherProfile !== null;

        return $dto;
    }

    /**
     * @param UserSocialLink[] $links
     * @return PublicProfileSocialLink[]
     */
    private function buildSocialLinks(array $links): array
    {
        return array_map(function (UserSocialLink $link): PublicProfileSocialLink {
            $dto = new PublicProfileSocialLink();
            $dto->platform = $link->platform->value;
            $dto->platformLabel = $link->platform->getLabel();
            $dto->url = $link->url;

            return $dto;
        }, $links);
    }

    /**
     * @param MusicianAnnounce[] $announces
     * @return PublicProfileAnnounce[]
     */
    private function buildAnnounces(array $announces): array
    {
        return array_map(function (MusicianAnnounce $announce): PublicProfileAnnounce {
            $dto = new PublicProfileAnnounce();
            $dto->id = (string) $announce->id;
            $dto->creationDatetime = $announce->creationDatetime;
            $dto->type = (int) $announce->type;
            $dto->instrumentName = (string) $announce->instrument->musicianName;
            $dto->locationName = (string) $announce->locationName;
            $dto->styles = array_map(
                fn($style) => (string) $style->name,
                $announce->styles->toArray()
            );

            return $dto;
        }, $announces);
    }
}
