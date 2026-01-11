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

    public function build(UserProfile $profile): PublicProfile
    {
        $user = $profile->getUser();
        $dto = new PublicProfile();

        $dto->username = $user->getUsername();
        $dto->userId = $user->getId();
        $dto->bio = $profile->getBio();
        $dto->location = $profile->getLocation();
        $dto->memberSince = $user->getCreationDatetime();

        // Profile picture
        if ($user->getProfilePicture()) {
            $path = $this->uploaderHelper->asset($user->getProfilePicture(), 'imageFile');
            $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            $dto->profilePictureLargeUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_large');
        }

        // Cover picture
        if ($profile->getCoverPicture()) {
            $path = $this->uploaderHelper->asset($profile->getCoverPicture(), 'imageFile');
            $dto->coverPictureUrl = $this->cacheManager->getBrowserPath($path, 'user_cover_picture');
        }

        // Social links
        $dto->socialLinks = $this->buildSocialLinks($profile->getSocialLinks()->toArray());

        // Musician announces
        $announces = $this->musicianAnnounceRepository->findBy(['author' => $user], ['creationDatetime' => 'DESC']);
        $dto->musicianAnnounces = $this->buildAnnounces($announces);

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
            $dto->platform = $link->getPlatform()->value;
            $dto->platformLabel = $link->getPlatform()->getLabel();
            $dto->url = $link->getUrl();

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
            $dto->id = $announce->getId();
            $dto->creationDatetime = $announce->getCreationDatetime();
            $dto->type = $announce->getType();
            $dto->instrumentName = $announce->getInstrument()->getMusicianName();
            $dto->locationName = $announce->getLocationName();
            $dto->styles = array_map(
                fn($style) => $style->getName(),
                $announce->getStyles()->toArray()
            );

            return $dto;
        }, $announces);
    }
}
