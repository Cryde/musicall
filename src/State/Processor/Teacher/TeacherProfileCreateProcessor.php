<?php

declare(strict_types=1);

namespace App\State\Processor\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Teacher\Private\TeacherProfileInput;
use App\ApiResource\Teacher\Private\TeacherProfileOutput;
use App\Entity\Teacher\TeacherAvailability;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfileInstrument;
use App\Entity\Teacher\TeacherProfileLocation;
use App\Entity\Teacher\TeacherProfilePackage;
use App\Entity\Teacher\TeacherProfilePricing;
use App\Entity\User;
use App\Enum\Teacher\DayOfWeek;
use App\Enum\Teacher\LocationType;
use App\Enum\Teacher\SessionDuration;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;
use App\State\Provider\Teacher\TeacherProfileEditProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<TeacherProfileInput, TeacherProfileOutput>
 */
readonly class TeacherProfileCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private InstrumentRepository $instrumentRepository,
        private StyleRepository $styleRepository,
        private TeacherProfileEditProvider $outputProvider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TeacherProfileOutput
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException();
        }

        if ($user->getTeacherProfile()) {
            throw new BadRequestHttpException('Vous avez déjà un profil professeur');
        }

        $profile = new TeacherProfile();
        $profile->setUser($user);

        $this->updateProfile($profile, $data);

        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $this->outputProvider->buildFromEntity($profile);
    }

    private function updateProfile(TeacherProfile $profile, TeacherProfileInput $data): void
    {
        $profile->setDescription($data->description);
        $profile->setYearsOfExperience($data->yearsOfExperience);

        // Student levels
        $profile->setStudentLevels($data->studentLevels);

        // Age groups
        $profile->setAgeGroups($data->ageGroups);

        // Course title
        $profile->setCourseTitle($data->courseTitle);

        // Trial lesson
        $profile->setOffersTrial($data->offersTrial);
        $profile->setTrialPrice($data->trialPrice);

        // Locations
        foreach ($profile->getLocations()->toArray() as $location) {
            $profile->removeLocation($location);
        }

        foreach ($data->locations as $locationInput) {
            $locationType = LocationType::tryFrom($locationInput->type ?? '');
            if (!$locationType) {
                continue;
            }

            $location = new TeacherProfileLocation();
            $location->setType($locationType);
            $location->setAddress($locationInput->address);
            $location->setCity($locationInput->city);
            $location->setCountry($locationInput->country);
            $location->setLatitude($locationInput->latitude !== null ? (string) $locationInput->latitude : null);
            $location->setLongitude($locationInput->longitude !== null ? (string) $locationInput->longitude : null);
            $location->setRadius($locationInput->radius);
            $profile->addLocation($location);
        }

        // Instruments
        foreach ($profile->getInstruments()->toArray() as $instrument) {
            $profile->removeInstrument($instrument);
        }

        foreach ($data->instrumentIds as $instrumentId) {
            $instrument = $this->instrumentRepository->find($instrumentId);
            if (!$instrument) {
                continue;
            }

            $profileInstrument = new TeacherProfileInstrument();
            $profileInstrument->setInstrument($instrument);
            $profile->addInstrument($profileInstrument);
        }

        // Styles
        foreach ($profile->getStyles()->toArray() as $style) {
            $profile->removeStyle($style);
        }

        foreach ($data->styleIds as $styleId) {
            $style = $this->styleRepository->find($styleId);
            if ($style) {
                $profile->addStyle($style);
            }
        }

        // Pricing
        foreach ($profile->getPricing()->toArray() as $pricing) {
            $profile->removePricing($pricing);
        }

        foreach ($data->pricing as $pricingInput) {
            $duration = SessionDuration::tryFrom($pricingInput->duration ?? '');
            if (!$duration || $pricingInput->price === null) {
                continue;
            }

            $pricing = new TeacherProfilePricing();
            $pricing->setDuration($duration);
            $pricing->setPrice($pricingInput->price);
            $profile->addPricing($pricing);
        }

        // Availability
        foreach ($profile->getAvailability()->toArray() as $availability) {
            $profile->removeAvailability($availability);
        }

        foreach ($data->availability as $availabilityInput) {
            $dayOfWeek = DayOfWeek::tryFrom($availabilityInput->dayOfWeek ?? '');
            if (!$dayOfWeek || $availabilityInput->startTime === null || $availabilityInput->endTime === null) {
                continue;
            }

            $availability = new TeacherAvailability();
            $availability->setDayOfWeek($dayOfWeek);
            $availability->setStartTime(new DateTimeImmutable($availabilityInput->startTime));
            $availability->setEndTime(new DateTimeImmutable($availabilityInput->endTime));
            $profile->addAvailability($availability);
        }

        // Packages
        foreach ($profile->getPackages()->toArray() as $package) {
            $profile->removePackage($package);
        }

        foreach ($data->packages as $packageInput) {
            if (empty($packageInput->title)) {
                continue;
            }

            $package = new TeacherProfilePackage();
            $package->setTitle($packageInput->title);
            $package->setDescription($packageInput->description);
            $package->setSessionsCount($packageInput->sessionsCount);
            $package->setPrice($packageInput->price ?? 0);
            $profile->addPackage($package);
        }

        $profile->setUpdateDatetime(new DateTimeImmutable());
    }
}
