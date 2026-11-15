<?php

declare(strict_types=1);

namespace App\Service\Builder\Teacher;

use App\ApiResource\Teacher\Public\FeaturedTeacher;
use App\ApiResource\Teacher\Public\TeacherProfileInstrument;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceInstrument;
use App\Entity\Service\TeachingDetails;
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
     * @param Service[] $profiles
     *
     * @return FeaturedTeacher[]
     */
    public function buildList(array $profiles): array
    {
        return array_map($this->build(...), $profiles);
    }

    private function build(Service $profile): FeaturedTeacher
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

        $dto->instruments = array_values(array_map(function (ServiceInstrument $instrument): TeacherProfileInstrument {
            $dto = new TeacherProfileInstrument();
            $dto->instrumentId = (string) $instrument->instrument->id;
            $dto->instrumentName = $this->referenceNames->instrument($instrument->instrument);

            return $dto;
        }, $profile->instruments->toArray()));

        $details = TeachingDetails::of($profile);
        $dto->offersTrial = $details->offersTrial;
        $dto->trialPrice = $details->trialPrice;

        return $dto;
    }
}
