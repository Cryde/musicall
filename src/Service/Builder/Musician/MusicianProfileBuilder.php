<?php

declare(strict_types=1);

namespace App\Service\Builder\Musician;

use App\ApiResource\Musician\PublicMusicianProfile;
use App\ApiResource\Musician\PublicMusicianProfileStyle;
use App\ApiResource\User\Profile\PublicProfileAnnounce;
use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Musician\MusicianProfile;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Translation\ReferenceNameTranslator;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class MusicianProfileBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private MusicianAnnounceRepository $musicianAnnounceRepository,
        private MusicianProfileMediaResourceBuilder $musicianProfileMediaResourceBuilder,
        private TranslatorInterface $translator,
        private ReferenceNameTranslator $referenceNames,
        private MusicianInstrumentResourceBuilder $musicianInstrumentResourceBuilder,
        private MusicianBandResourceBuilder $musicianBandResourceBuilder,
    ) {
    }

    public function build(MusicianProfile $profile): PublicMusicianProfile
    {
        $user = $profile->user;
        $dto = new PublicMusicianProfile();

        $dto->username = $user->username;
        $dto->userId = $user->id;
        $dto->creationDatetime = $profile->creationDatetime;
        $dto->updateDatetime = $profile->updateDatetime;

        // Profile picture
        if ($user->profilePicture instanceof \App\Entity\Image\UserProfilePicture) {
            $path = $this->uploaderHelper->asset($user->profilePicture, 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        if ($profile->availabilityStatus instanceof \App\Enum\Musician\AvailabilityStatus) {
            $dto->availabilityStatus = $profile->availabilityStatus->value;
            $dto->availabilityStatusLabel = $profile->availabilityStatus->trans($this->translator);
        }

        $dto->instruments = $this->musicianInstrumentResourceBuilder->buildList($profile->instruments);
        $dto->styles = $this->buildStyles($profile->styles->toArray());
        $dto->bands = $this->musicianBandResourceBuilder->buildList($profile->bands);
        $dto->media = $this->musicianProfileMediaResourceBuilder->buildList($profile->media->toArray());

        // Musician announces
        $announces = $this->musicianAnnounceRepository->findBy(['author' => $user], ['creationDatetime' => 'DESC']);
        $dto->musicianAnnounces = $this->buildAnnounces($announces);

        return $dto;
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
            $dto->name = $this->referenceNames->style($style);

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
            $dto->type = $announce->type;
            $dto->instrumentName = $this->referenceNames->instrumentMusicianName($announce->instrument);
            $dto->locationName = $announce->locationName;
            $dto->styles = array_map(
                $this->referenceNames->style(...),
                $announce->styles->toArray()
            );

            return $dto;
        }, $announces);
    }
}
