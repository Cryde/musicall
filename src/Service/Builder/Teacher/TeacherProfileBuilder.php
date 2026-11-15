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
use App\Entity\Musician\MusicianProfile;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceAvailability;
use App\Entity\Service\ServiceInstrument;
use App\Entity\Service\ServiceLink;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\OfferUnit;
use App\Enum\Teacher\LocationType;
use App\Enum\Teacher\SessionDuration;
use App\Service\Builder\Musician\MusicianBandResourceBuilder;
use App\Service\Builder\Musician\MusicianInstrumentResourceBuilder;
use App\Translation\ReferenceNameTranslator;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class TeacherProfileBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private TeacherProfileMediaResourceBuilder $teacherProfileMediaResourceBuilder,
        private ReferenceNameTranslator $referenceNames,
        private MusicianInstrumentResourceBuilder $musicianInstrumentResourceBuilder,
        private MusicianBandResourceBuilder $musicianBandResourceBuilder,
    ) {
    }

    public function build(Service $profile): PublicTeacherProfile
    {
        $user = $profile->user;
        $dto = new PublicTeacherProfile();

        $dto->username = $user->username;
        $dto->userId = $user->id;
        $dto->displayName = $user->profile->displayName ?: $user->username;
        $dto->memberSince = $user->creationDatetime;
        $dto->isPublic = $user->profile->isPublic;
        $dto->musicianInstruments = $user->musicianProfile instanceof MusicianProfile
            ? $this->musicianInstrumentResourceBuilder->buildList($user->musicianProfile->instruments)
            : [];
        $dto->musicianBands = $user->musicianProfile instanceof MusicianProfile
            ? $this->musicianBandResourceBuilder->buildList(array_filter($user->musicianProfile->bands, static fn (array $band): bool => $band['current']))
            : [];
        $dto->creationDatetime = $profile->creationDatetime;
        $dto->updateDatetime = $profile->updateDatetime;

        // Profile picture
        if ($user->profilePicture instanceof \App\Entity\Image\UserProfilePicture) {
            $path = $this->uploaderHelper->asset($user->profilePicture, 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        $dto->description = $profile->description;
        $dto->yearsOfExperience = $profile->yearsOfExperience;

        $details = TeachingDetails::of($profile);
        $dto->studentLevels = $details->studentLevels;
        $dto->ageGroups = $details->ageGroups;

        $dto->courseTitle = $profile->headline;
        $dto->offersTrial = $details->offersTrial;
        $dto->trialPrice = $details->trialPrice;

        $dto->locations = $this->buildLocations($profile->places->toArray());

        $dto->instruments = $this->buildInstruments($profile->instruments->toArray());
        $dto->styles = $this->buildStyles($profile->styles->toArray());
        $dto->media = $this->teacherProfileMediaResourceBuilder->buildList($profile->media->toArray());
        $dto->pricing = $this->buildPricing($profile->offersIn(OfferUnit::Lesson));
        $dto->availability = $this->buildAvailability($profile->availability->toArray());
        $dto->packages = $this->buildPackages($profile->offersIn(OfferUnit::Package));
        $dto->socialLinks = $this->buildSocialLinks($profile->links->toArray());
        $dto->levelGoals = $details->levelGoalList();
        $dto->songs = $details->songs;

        return $dto;
    }

    /**
     * @param ServiceInstrument[] $instruments
     * @return PublicTeacherProfileInstrument[]
     */
    public function buildInstruments(array $instruments): array
    {
        return array_values(array_map(function (ServiceInstrument $instrument): PublicTeacherProfileInstrument {
            $dto = new PublicTeacherProfileInstrument();
            $dto->instrumentId = (string) $instrument->instrument->id;
            $dto->instrumentName = $this->referenceNames->instrument($instrument->instrument);

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
            $dto->name = $this->referenceNames->style($style);

            return $dto;
        }, $styles));
    }

    /**
     * @param ServicePlace[] $locations
     * @return PublicTeacherProfileLocation[]
     */
    private function buildLocations(array $locations): array
    {
        return array_values(array_map(function (ServicePlace $location): PublicTeacherProfileLocation {
            $dto = new PublicTeacherProfileLocation();
            $dto->type = LocationType::fromPlaceType($location->type)->value;
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
     * @param ServiceOffer[] $pricing lesson offers
     * @return PublicTeacherProfilePricing[]
     */
    public function buildPricing(array $pricing): array
    {
        return array_values(array_map(function (ServiceOffer $p): PublicTeacherProfilePricing {
            $dto = new PublicTeacherProfilePricing();
            $dto->duration = SessionDuration::fromMinutes($p->quantity)->value;
            $dto->price = $p->price;

            return $dto;
        }, $pricing));
    }

    /**
     * @param ServiceAvailability[] $availability
     * @return PublicTeacherAvailability[]
     */
    public function buildAvailability(array $availability): array
    {
        $result = array_values(array_map(function (ServiceAvailability $a): PublicTeacherAvailability {
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
     * @param ServiceOffer[] $packages package offers
     * @return PublicTeacherProfilePackage[]
     */
    private function buildPackages(array $packages): array
    {
        return array_values(array_map(function (ServiceOffer $package): PublicTeacherProfilePackage {
            $dto = new PublicTeacherProfilePackage();
            $dto->id = (string) $package->id;
            $dto->title = (string) $package->title;
            $dto->description = $package->description;
            $dto->sessionsCount = $package->quantity;
            $dto->price = $package->price;

            return $dto;
        }, $packages));
    }

    /**
     * @param ServiceLink[] $socialLinks
     * @return PublicTeacherProfileSocialLink[]
     */
    private function buildSocialLinks(array $socialLinks): array
    {
        return array_values(array_map(function (ServiceLink $link): PublicTeacherProfileSocialLink {
            $dto = new PublicTeacherProfileSocialLink();
            $dto->platform = $link->platform->value;
            $dto->url = $link->url;

            return $dto;
        }, $socialLinks));
    }
}
