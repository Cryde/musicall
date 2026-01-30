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
use App\Entity\User\UserSocialLink;
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
        $user = $profile->getUser();
        $dto = new PublicTeacherProfile();

        $dto->username = $user->getUsername();
        $dto->userId = (string) $user->getId();
        $dto->creationDatetime = $profile->getCreationDatetime();
        $dto->updateDatetime = $profile->getUpdateDatetime();

        // Profile picture
        if ($user->getProfilePicture()) {
            $path = $this->uploaderHelper->asset($user->getProfilePicture(), 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        $dto->description = $profile->getDescription();
        $dto->yearsOfExperience = $profile->getYearsOfExperience();

        $dto->studentLevels = $profile->getStudentLevels();
        $dto->ageGroups = $profile->getAgeGroups();

        $dto->courseTitle = $profile->getCourseTitle();
        $dto->offersTrial = $profile->offersTrial();
        $dto->trialPrice = $profile->getTrialPrice();

        $dto->locations = $this->buildLocations($profile->getLocations()->toArray());

        $dto->instruments = $this->buildInstruments($profile->getInstruments()->toArray());
        $dto->styles = $this->buildStyles($profile->getStyles()->toArray());
        $dto->media = $this->teacherProfileMediaResourceBuilder->buildList($profile->getMedia()->toArray());
        $dto->pricing = $this->buildPricing($profile->getPricing()->toArray());
        $dto->availability = $this->buildAvailability($profile->getAvailability()->toArray());
        $dto->packages = $this->buildPackages($profile->getPackages()->toArray());

        // Get social links from user's profile
        $userProfile = $user->getProfile();
        $dto->socialLinks = $this->buildSocialLinks($userProfile->getSocialLinks()->toArray());

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
            $dto->instrumentId = (string) $instrument->getInstrument()->getId();
            $dto->instrumentName = (string) $instrument->getInstrument()->getName();

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
            $dto->id = (string) $style->getId();
            $dto->name = (string) $style->getName();

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
            $dto->type = $location->getType()->value;
            $dto->address = $location->getAddress();
            $dto->city = $location->getCity();
            $dto->country = $location->getCountry();
            $dto->latitude = $location->getLatitude();
            $dto->longitude = $location->getLongitude();
            $dto->radius = $location->getRadius();

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
            $dto->duration = $p->getDuration()->value;
            $dto->price = $p->getPrice();

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
            $dto->dayOfWeek = $a->getDayOfWeek()->value;
            $dto->dayOfWeekOrder = $a->getDayOfWeek()->getOrder();
            $dto->startTime = $a->getStartTime()->format('H:i');
            $dto->endTime = $a->getEndTime()->format('H:i');

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
            $dto->id = (string) $package->getId();
            $dto->title = $package->getTitle();
            $dto->description = $package->getDescription();
            $dto->sessionsCount = $package->getSessionsCount();
            $dto->price = $package->getPrice();

            return $dto;
        }, $packages));
    }

    /**
     * @param UserSocialLink[] $socialLinks
     * @return PublicTeacherProfileSocialLink[]
     */
    private function buildSocialLinks(array $socialLinks): array
    {
        return array_values(array_map(function (UserSocialLink $link): PublicTeacherProfileSocialLink {
            $dto = new PublicTeacherProfileSocialLink();
            $dto->platform = $link->getPlatform()->value;
            $dto->url = $link->getUrl();

            return $dto;
        }, $socialLinks));
    }
}
