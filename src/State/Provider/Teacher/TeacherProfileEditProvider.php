<?php

declare(strict_types=1);

namespace App\State\Provider\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Teacher\Private\TeacherProfileAvailability;
use App\ApiResource\Teacher\Private\TeacherProfileInstrument;
use App\ApiResource\Teacher\Private\TeacherProfileLocation;
use App\ApiResource\Teacher\Private\TeacherProfileOutput;
use App\ApiResource\Teacher\Private\TeacherProfilePackage;
use App\ApiResource\Teacher\Private\TeacherProfilePricing;
use App\ApiResource\Teacher\Private\TeacherProfileSocialLink;
use App\ApiResource\Teacher\Private\TeacherProfileStyle;
use App\Entity\Attribute\Style;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceAvailability;
use App\Entity\Service\ServiceInstrument;
use App\Entity\Service\ServiceLink;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\TeachingDetails;
use App\Entity\User;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceKind;
use App\Enum\Teacher\LocationType;
use App\Enum\Teacher\SessionDuration;
use App\Repository\Service\ServiceRepository;
use App\Service\Service\YearsOfExperience;
use App\Translation\ReferenceNameTranslator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<TeacherProfileOutput>
 */
readonly class TeacherProfileEditProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ReferenceNameTranslator $referenceNames,
        private ServiceRepository $serviceRepository,
        private YearsOfExperience $yearsOfExperience,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TeacherProfileOutput
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if (!$teacherProfile = $this->serviceRepository->findOneByUserAndKind($user, ServiceKind::Teaching)) {
            throw new NotFoundHttpException('profile.teacher.not_found');
        }

        return $this->buildFromEntity($teacherProfile);
    }

    public function buildFromEntity(Service $profile): TeacherProfileOutput
    {
        $details = TeachingDetails::of($profile);
        $dto = new TeacherProfileOutput();
        $dto->id = $profile->id;
        $dto->description = $profile->description;
        $dto->yearsOfExperience = $this->yearsOfExperience->since($profile->experienceStartYear);
        $dto->experienceStartYear = $profile->experienceStartYear;

        $dto->studentLevels = $details->studentLevels;
        $dto->ageGroups = $details->ageGroups;

        $dto->courseTitle = $profile->headline;
        $dto->offersTrial = $details->offersTrial;
        $dto->trialPrice = $details->trialPrice;

        $dto->locations = array_values(array_map(function (ServicePlace $location): TeacherProfileLocation {
            $item = new TeacherProfileLocation();
            $item->id = $location->id;
            $item->type = LocationType::fromPlaceType($location->type)->value;
            $item->address = $location->address;
            $item->city = $location->city;
            $item->country = $location->country;
            $item->latitude = $location->latitude;
            $item->longitude = $location->longitude;
            $item->radius = $location->radius;

            return $item;
        }, $profile->places->toArray()));

        $dto->instruments = array_values(array_map(function (ServiceInstrument $instrument): TeacherProfileInstrument {
            $item = new TeacherProfileInstrument();
            $item->instrumentId = (string) $instrument->instrument->id;
            $item->instrumentName = $this->referenceNames->instrumentMusicianName($instrument->instrument);

            return $item;
        }, $profile->instruments->toArray()));

        $dto->styles = array_values(array_map(function (Style $style): TeacherProfileStyle {
            $item = new TeacherProfileStyle();
            $item->id = (string) $style->id;
            $item->name = $this->referenceNames->style($style);

            return $item;
        }, $profile->styles->toArray()));

        $dto->pricing = array_map(function (ServiceOffer $pricing): TeacherProfilePricing {
            $item = new TeacherProfilePricing();
            $item->id = $pricing->id;
            $item->duration = SessionDuration::fromMinutes($pricing->quantity)->value;
            $item->price = $pricing->price;

            return $item;
        }, $profile->offersIn(OfferUnit::Lesson));

        $dto->availability = array_values(array_map(function (ServiceAvailability $availability): TeacherProfileAvailability {
            $item = new TeacherProfileAvailability();
            $item->id = $availability->id;
            $item->dayOfWeek = $availability->dayOfWeek->value;
            $item->startTime = $availability->startTime->format('H:i');
            $item->endTime = $availability->endTime->format('H:i');

            return $item;
        }, $profile->availability->toArray()));

        $dto->packages = array_map(function (ServiceOffer $package): TeacherProfilePackage {
            $item = new TeacherProfilePackage();
            $item->id = $package->id;
            $item->title = (string) $package->title;
            $item->description = $package->description;
            $item->sessionsCount = $package->quantity;
            $item->price = $package->price;

            return $item;
        }, $profile->offersIn(OfferUnit::Package));

        $dto->socialLinks = array_values(array_map(function (ServiceLink $socialLink): TeacherProfileSocialLink {
            $item = new TeacherProfileSocialLink();
            $item->id = $socialLink->id;
            $item->platform = $socialLink->platform->value;
            $item->url = $socialLink->url;

            return $item;
        }, $profile->links->toArray()));

        $dto->levelGoals = $details->levelGoalList();
        $dto->songs = $details->songs;

        return $dto;
    }
}
