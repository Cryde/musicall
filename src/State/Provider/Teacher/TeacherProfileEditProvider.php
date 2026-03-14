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
use App\Entity\Teacher\TeacherProfile;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<TeacherProfileOutput>
 */
readonly class TeacherProfileEditProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TeacherProfileOutput
    {
        if (!$user = $this->security->getUser()) {
            throw new AccessDeniedHttpException();
        }
        /** @var User $user */
        if (!$teacherProfile = $user->teacherProfile) {
            throw new NotFoundHttpException('Profil professeur non trouvé');
        }

        return $this->buildFromEntity($teacherProfile);
    }

    public function buildFromEntity(TeacherProfile $profile): TeacherProfileOutput
    {
        $dto = new TeacherProfileOutput();
        /** @var string|null $profileId */
        $profileId = $profile->id;
        $dto->id = $profileId;
        $dto->description = $profile->description;
        $dto->yearsOfExperience = $profile->yearsOfExperience;

        $dto->studentLevels = $profile->studentLevels;
        $dto->ageGroups = $profile->ageGroups;

        $dto->courseTitle = $profile->courseTitle;
        $dto->offersTrial = $profile->offersTrial;
        $dto->trialPrice = $profile->trialPrice;

        $dto->locations = array_values(array_map(function ($location): TeacherProfileLocation {
            $item = new TeacherProfileLocation();
            /** @var string|null $locationId */
            $locationId = $location->id;
            $item->id = $locationId;
            $item->type = $location->type->value;
            $item->address = $location->address;
            $item->city = $location->city;
            $item->country = $location->country;
            $item->latitude = $location->latitude;
            $item->longitude = $location->longitude;
            $item->radius = $location->radius;

            return $item;
        }, $profile->locations->toArray()));

        $dto->instruments = array_values(array_map(function ($instrument): TeacherProfileInstrument {
            $item = new TeacherProfileInstrument();
            $item->instrumentId = (string) $instrument->instrument->id;
            $item->instrumentName = (string) $instrument->instrument->musicianName;

            return $item;
        }, $profile->instruments->toArray()));

        $dto->styles = array_values(array_map(function ($style): TeacherProfileStyle {
            $item = new TeacherProfileStyle();
            $item->id = (string) $style->id;
            $item->name = (string) $style->name;

            return $item;
        }, $profile->styles->toArray()));

        $dto->pricing = array_values(array_map(function ($pricing): TeacherProfilePricing {
            $item = new TeacherProfilePricing();
            /** @var string|null $pricingId */
            $pricingId = $pricing->id;
            $item->id = $pricingId;
            $item->duration = $pricing->duration->value;
            $item->price = $pricing->price;

            return $item;
        }, $profile->pricing->toArray()));

        $dto->availability = array_values(array_map(function ($availability): TeacherProfileAvailability {
            $item = new TeacherProfileAvailability();
            /** @var string|null $availabilityId */
            $availabilityId = $availability->id;
            $item->id = $availabilityId;
            $item->dayOfWeek = $availability->dayOfWeek->value;
            $item->startTime = $availability->startTime->format('H:i');
            $item->endTime = $availability->endTime->format('H:i');

            return $item;
        }, $profile->availability->toArray()));

        $dto->packages = array_values(array_map(function ($package): TeacherProfilePackage {
            $item = new TeacherProfilePackage();
            /** @var string|null $packageId */
            $packageId = $package->id;
            $item->id = $packageId;
            $item->title = $package->title;
            $item->description = $package->description;
            $item->sessionsCount = $package->sessionsCount;
            $item->price = $package->price;

            return $item;
        }, $profile->packages->toArray()));

        $dto->socialLinks = array_values(array_map(function ($socialLink): TeacherProfileSocialLink {
            $item = new TeacherProfileSocialLink();
            $item->id = $socialLink->id;
            $item->platform = $socialLink->platform->value;
            $item->url = $socialLink->url;

            return $item;
        }, $profile->socialLinks->toArray()));

        return $dto;
    }
}
