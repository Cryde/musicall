<?php

declare(strict_types=1);

namespace App\Service\Builder\Teacher;

use App\ApiResource\Teacher\Public\FeaturedTeacher;
use App\ApiResource\Teacher\Public\TeacherProfileInstrument;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfileInstrument as TeacherProfileInstrumentEntity;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class FeaturedTeacherBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    /**
     * @param TeacherProfile[] $profiles
     *
     * @return FeaturedTeacher[]
     */
    public function buildList(array $profiles): array
    {
        return array_map($this->build(...), $profiles);
    }

    private function build(TeacherProfile $profile): FeaturedTeacher
    {
        $user = $profile->getUser();
        $dto = new FeaturedTeacher();

        $dto->username = $user->getUsername();

        if ($user->getProfilePicture()) {
            $path = $this->uploaderHelper->asset($user->getProfilePicture(), 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        $dto->instruments = array_values(array_map(static function (TeacherProfileInstrumentEntity $instrument): TeacherProfileInstrument {
            $dto = new TeacherProfileInstrument();
            $dto->instrumentId = (string) $instrument->getInstrument()->getId();
            $dto->instrumentName = (string) $instrument->getInstrument()->getName();

            return $dto;
        }, $profile->getInstruments()->toArray()));

        $dto->offersTrial = $profile->offersTrial();
        $dto->trialPrice = $profile->getTrialPrice();

        return $dto;
    }
}
