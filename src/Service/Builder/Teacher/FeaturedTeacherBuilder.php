<?php

declare(strict_types=1);

namespace App\Service\Builder\Teacher;

use App\ApiResource\Teacher\Public\FeaturedTeacher;
use App\ApiResource\Teacher\Public\TeacherProfileInstrument;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfileInstrument as TeacherProfileInstrumentEntity;
use App\Translation\ReferenceNameTranslator;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class FeaturedTeacherBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private ReferenceNameTranslator $referenceNames,
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
        $user = $profile->user;
        $dto = new FeaturedTeacher();

        $dto->username = $user->username;

        if ($user->profilePicture instanceof \App\Entity\Image\UserProfilePicture) {
            $path = $this->uploaderHelper->asset($user->profilePicture, 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        $dto->instruments = array_values(array_map(function (TeacherProfileInstrumentEntity $instrument): TeacherProfileInstrument {
            $dto = new TeacherProfileInstrument();
            $dto->instrumentId = (string) $instrument->instrument->id;
            $dto->instrumentName = $this->referenceNames->instrument($instrument->instrument);

            return $dto;
        }, $profile->instruments->toArray()));

        $dto->offersTrial = $profile->offersTrial;
        $dto->trialPrice = $profile->trialPrice;

        return $dto;
    }
}
