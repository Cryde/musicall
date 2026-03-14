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
        private MusicianProfileMediaResourceBuilder $musicianProfileMediaResourceBuilder,
    ) {
    }

    public function build(MusicianProfile $profile): PublicMusicianProfile
    {
        $user = $profile->user;
        $dto = new PublicMusicianProfile();

        $dto->username = $user->getUsername();
        $dto->userId = (string) $user->getId();
        $dto->creationDatetime = $profile->creationDatetime;
        $dto->updateDatetime = $profile->updateDatetime;

        // Profile picture
        if ($user->getProfilePicture()) {
            $path = $this->uploaderHelper->asset($user->getProfilePicture(), 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        if ($profile->availabilityStatus) {
            $dto->availabilityStatus = $profile->availabilityStatus->value;
            $dto->availabilityStatusLabel = $profile->availabilityStatus->getLabel();
        }

        $dto->instruments = $this->buildInstruments($profile->instruments->toArray());
        $dto->styles = $this->buildStyles($profile->styles->toArray());
        $dto->media = $this->musicianProfileMediaResourceBuilder->buildList($profile->media->toArray());

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
            $instrumentEntity = $instrument->instrument;
            $dto = new PublicMusicianProfileInstrument();
            $dto->instrumentId = (string) $instrumentEntity->id;
            $dto->instrumentName = (string) $instrumentEntity->musicianName;
            $dto->skillLevel = $instrument->skillLevel->value;
            $dto->skillLevelLabel = $instrument->skillLevel->getLabel();

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
            $dto->id = (string) $style->id;
            $dto->name = (string) $style->name;

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
