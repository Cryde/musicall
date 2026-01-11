<?php

declare(strict_types=1);

namespace App\Service\Builder\Musician;

use App\ApiResource\Musician\PublicMusicianProfile;
use App\ApiResource\Musician\PublicMusicianProfileInstrument;
use App\ApiResource\Musician\PublicMusicianProfileStyle;
use App\ApiResource\User\Profile\PublicProfileAnnounce;
use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Musician\MusicianProfile;
use App\Entity\Musician\MusicianProfileInstrument;
use App\Repository\Musician\MusicianAnnounceRepository;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class MusicianProfileBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private MusicianAnnounceRepository $musicianAnnounceRepository,
    ) {
    }

    public function build(MusicianProfile $profile): PublicMusicianProfile
    {
        $user = $profile->getUser();
        $dto = new PublicMusicianProfile();

        $dto->username = $user->getUsername();
        $dto->userId = $user->getId();
        $dto->creationDatetime = $profile->getCreationDatetime();
        $dto->updateDatetime = $profile->getUpdateDatetime();

        // Profile picture
        if ($user->getProfilePicture()) {
            $path = $this->uploaderHelper->asset($user->getProfilePicture(), 'imageFile');
            $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
        }

        if ($profile->getAvailabilityStatus()) {
            $dto->availabilityStatus = $profile->getAvailabilityStatus()->value;
            $dto->availabilityStatusLabel = $profile->getAvailabilityStatus()->getLabel();
        }

        $dto->instruments = $this->buildInstruments($profile->getInstruments()->toArray());
        $dto->styles = $this->buildStyles($profile->getStyles()->toArray());

        // Musician announces
        $announces = $this->musicianAnnounceRepository->findBy(['author' => $user], ['creationDatetime' => 'DESC']);
        $dto->musicianAnnounces = $this->buildAnnounces($announces);

        return $dto;
    }

    /**
     * @param MusicianProfileInstrument[] $instruments
     * @return PublicMusicianProfileInstrument[]
     */
    private function buildInstruments(array $instruments): array
    {
        return array_values(array_map(function (MusicianProfileInstrument $instrument): PublicMusicianProfileInstrument {
            $dto = new PublicMusicianProfileInstrument();
            $dto->instrumentId = $instrument->getInstrument()->getId();
            $dto->instrumentName = $instrument->getInstrument()->getMusicianName();
            $dto->skillLevel = $instrument->getSkillLevel()->value;
            $dto->skillLevelLabel = $instrument->getSkillLevel()->getLabel();

            return $dto;
        }, $instruments));
    }

    /**
     * @param Style[] $styles
     * @return PublicMusicianProfileStyle[]
     */
    private function buildStyles(array $styles): array
    {
        return array_values(array_map(function (Style $style): PublicMusicianProfileStyle {
            $dto = new PublicMusicianProfileStyle();
            $dto->id = $style->getId();
            $dto->name = $style->getName();

            return $dto;
        }, $styles));
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
