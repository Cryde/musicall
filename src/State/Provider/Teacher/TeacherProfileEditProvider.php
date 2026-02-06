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
        if (!$teacherProfile = $user->getTeacherProfile()) {
            throw new NotFoundHttpException('Profil professeur non trouvé');
        }

        return $this->buildFromEntity($teacherProfile);
    }

    public function buildFromEntity(TeacherProfile $profile): TeacherProfileOutput
    {
        $dto = new TeacherProfileOutput();
        $dto->id = $profile->getId();
        $dto->description = $profile->getDescription();
        $dto->yearsOfExperience = $profile->getYearsOfExperience();

        $dto->studentLevels = $profile->getStudentLevels();
        $dto->ageGroups = $profile->getAgeGroups();

        $dto->courseTitle = $profile->getCourseTitle();
        $dto->offersTrial = $profile->offersTrial();
        $dto->trialPrice = $profile->getTrialPrice();

        $dto->locations = array_values(array_map(function ($location): TeacherProfileLocation {
            $item = new TeacherProfileLocation();
            $item->id = $location->getId();
            $item->type = $location->getType()->value;
            $item->address = $location->getAddress();
            $item->city = $location->getCity();
            $item->country = $location->getCountry();
            $item->latitude = $location->getLatitude();
            $item->longitude = $location->getLongitude();
            $item->radius = $location->getRadius();

            return $item;
        }, $profile->getLocations()->toArray()));

        $dto->instruments = array_values(array_map(function ($instrument): TeacherProfileInstrument {
            $item = new TeacherProfileInstrument();
            $item->instrumentId = (string) $instrument->getInstrument()->getId();
            $item->instrumentName = (string) $instrument->getInstrument()->getMusicianName();

            return $item;
        }, $profile->getInstruments()->toArray()));

        $dto->styles = array_values(array_map(function ($style): TeacherProfileStyle {
            $item = new TeacherProfileStyle();
            $item->id = (string) $style->getId();
            $item->name = (string) $style->getName();

            return $item;
        }, $profile->getStyles()->toArray()));

        $dto->pricing = array_values(array_map(function ($pricing): TeacherProfilePricing {
            $item = new TeacherProfilePricing();
            $item->id = $pricing->getId();
            $item->duration = $pricing->getDuration()->value;
            $item->price = $pricing->getPrice();

            return $item;
        }, $profile->getPricing()->toArray()));

        $dto->availability = array_values(array_map(function ($availability): TeacherProfileAvailability {
            $item = new TeacherProfileAvailability();
            $item->id = $availability->getId();
            $item->dayOfWeek = $availability->getDayOfWeek()->value;
            $item->startTime = $availability->getStartTime()->format('H:i');
            $item->endTime = $availability->getEndTime()->format('H:i');

            return $item;
        }, $profile->getAvailability()->toArray()));

        $dto->packages = array_values(array_map(function ($package): TeacherProfilePackage {
            $item = new TeacherProfilePackage();
            $item->id = $package->getId();
            $item->title = $package->getTitle();
            $item->description = $package->getDescription();
            $item->sessionsCount = $package->getSessionsCount();
            $item->price = $package->getPrice();

            return $item;
        }, $profile->getPackages()->toArray()));

        $dto->socialLinks = array_values(array_map(function ($socialLink): TeacherProfileSocialLink {
            $item = new TeacherProfileSocialLink();
            $item->id = $socialLink->getId();
            $item->platform = $socialLink->getPlatform()->value;
            $item->url = $socialLink->getUrl();

            return $item;
        }, $profile->getSocialLinks()->toArray()));

        return $dto;
    }
}
