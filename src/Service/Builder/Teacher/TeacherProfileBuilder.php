<?php

declare(strict_types=1);

namespace App\Service\Builder\Teacher;

use App\ApiResource\Teacher\Public\TeacherProfile as PublicTeacherProfile;
use App\ApiResource\Teacher\Public\TeacherProfileAvailability as PublicTeacherAvailability;
use App\ApiResource\Teacher\Public\TeacherProfileInstrument as PublicTeacherProfileInstrument;
use App\ApiResource\Teacher\Public\TeacherProfileLocation as PublicTeacherProfileLocation;
use App\ApiResource\Teacher\Public\TeacherProfilePackage as PublicTeacherProfilePackage;
use App\ApiResource\Teacher\Public\TeacherProfilePricing as PublicTeacherProfilePricing;
use App\ApiResource\Teacher\Public\TeacherProfileSocialLink as PublicTeacherProfileSocialLink;
use App\ApiResource\Teacher\Public\TeacherProfileStyle as PublicTeacherProfileStyle;
use App\Entity\Attribute\Style;
use App\Entity\Teacher\TeacherAvailability;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfileInstrument;
use App\Entity\Teacher\TeacherProfileLocation;
use App\Entity\Teacher\TeacherProfilePackage;
use App\Entity\Teacher\TeacherProfilePricing;
use App\Entity\Teacher\TeacherSocialLink;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class TeacherProfileBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private TeacherProfileMediaResourceBuilder $teacherProfileMediaResourceBuilder,
    ) {
    }

    public function build(TeacherProfile $profile): PublicTeacherProfile
    {
        $user = $profile->user;
        $dto = new PublicTeacherProfile();

        $dto->username = $user->username;
        $dto->userId = (string) $user->id;
        $dto->creationDatetime = $profile->creationDatetime;
        $dto->updateDatetime = $profile->updateDatetime;

        // Profile picture
        if ($user->profilePicture) {
            $path = $this->uploaderHelper->asset($user->profilePicture, 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        $dto->description = $profile->description;
        $dto->yearsOfExperience = $profile->yearsOfExperience;

        $dto->studentLevels = $profile->studentLevels;
        $dto->ageGroups = $profile->ageGroups;

        $dto->courseTitle = $profile->courseTitle;
        $dto->offersTrial = $profile->offersTrial;
        $dto->trialPrice = $profile->trialPrice;

        $dto->locations = $this->buildLocations($profile->locations->toArray());

        $dto->instruments = $this->buildInstruments($profile->instruments->toArray());
        $dto->styles = $this->buildStyles($profile->styles->toArray());
        $dto->media = $this->teacherProfileMediaResourceBuilder->buildList($profile->media->toArray());
        $dto->pricing = $this->buildPricing($profile->pricing->toArray());
        $dto->availability = $this->buildAvailability($profile->availability->toArray());
        $dto->packages = $this->buildPackages($profile->packages->toArray());
        $dto->socialLinks = $this->buildSocialLinks($profile->socialLinks->toArray());

        return $dto;
    }

    /**
     * @param TeacherProfileInstrument[] $instruments
     * @return PublicTeacherProfileInstrument[]
     */
    private function buildInstruments(array $instruments): array
    {
        return array_values(array_map(function (TeacherProfileInstrument $instrument): PublicTeacherProfileInstrument {
            $dto = new PublicTeacherProfileInstrument();
            $dto->instrumentId = (string) $instrument->instrument->id;
            $dto->instrumentName = (string) $instrument->instrument->name;

            return $dto;
        }, $instruments));
    }

    /**
     * @param Style[] $styles
     * @return PublicTeacherProfileStyle[]
     */
    private function buildStyles(array $styles): array
    {
        return array_values(array_map(function (Style $style): PublicTeacherProfileStyle {
            $dto = new PublicTeacherProfileStyle();
            $dto->id = (string) $style->id;
            $dto->name = (string) $style->name;

            return $dto;
        }, $styles));
    }

    /**
     * @param TeacherProfileLocation[] $locations
     * @return PublicTeacherProfileLocation[]
     */
    private function buildLocations(array $locations): array
    {
        return array_values(array_map(function (TeacherProfileLocation $location): PublicTeacherProfileLocation {
            $dto = new PublicTeacherProfileLocation();
            $dto->type = $location->type->value;
            $dto->address = $location->address;
            $dto->city = $location->city;
            $dto->country = $location->country;
            $dto->latitude = $location->latitude;
            $dto->longitude = $location->longitude;
            $dto->radius = $location->radius;

            return $dto;
        }, $locations));
    }

    /**
     * @param TeacherProfilePricing[] $pricing
     * @return PublicTeacherProfilePricing[]
     */
    private function buildPricing(array $pricing): array
    {
        return array_values(array_map(function (TeacherProfilePricing $p): PublicTeacherProfilePricing {
            $dto = new PublicTeacherProfilePricing();
            $dto->duration = $p->duration->value;
            $dto->price = $p->price;

            return $dto;
        }, $pricing));
    }

    /**
     * @param TeacherAvailability[] $availability
     * @return PublicTeacherAvailability[]
     */
    private function buildAvailability(array $availability): array
    {
        $result = array_values(array_map(function (TeacherAvailability $a): PublicTeacherAvailability {
            $dto = new PublicTeacherAvailability();
            $dto->dayOfWeek = $a->dayOfWeek->value;
            $dto->dayOfWeekOrder = $a->dayOfWeek->getOrder();
            $dto->startTime = $a->startTime->format('H:i');
            $dto->endTime = $a->endTime->format('H:i');

            return $dto;
        }, $availability));

        // Sort by day of week order, then by start time
        usort($result, function (PublicTeacherAvailability $a, PublicTeacherAvailability $b): int {
            if ($a->dayOfWeekOrder !== $b->dayOfWeekOrder) {
                return $a->dayOfWeekOrder <=> $b->dayOfWeekOrder;
            }

            return $a->startTime <=> $b->startTime;
        });

        return $result;
    }

    /**
     * @param TeacherProfilePackage[] $packages
     * @return PublicTeacherProfilePackage[]
     */
    private function buildPackages(array $packages): array
    {
        return array_values(array_map(function (TeacherProfilePackage $package): PublicTeacherProfilePackage {
            $dto = new PublicTeacherProfilePackage();
            $dto->id = (string) $package->id;
            $dto->title = $package->title;
            $dto->description = $package->description;
            $dto->sessionsCount = $package->sessionsCount;
            $dto->price = $package->price;

            return $dto;
        }, $packages));
    }

    /**
     * @param TeacherSocialLink[] $socialLinks
     * @return PublicTeacherProfileSocialLink[]
     */
    private function buildSocialLinks(array $socialLinks): array
    {
        return array_values(array_map(function (TeacherSocialLink $link): PublicTeacherProfileSocialLink {
            $dto = new PublicTeacherProfileSocialLink();
            $dto->platform = $link->platform->value;
            $dto->url = $link->url;

            return $dto;
        }, $socialLinks));
    }
}
