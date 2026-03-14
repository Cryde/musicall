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
use App\Entity\Teacher\TeacherSocialLink;
use App\Entity\User;
use App\Enum\SocialPlatform;
use App\Enum\Teacher\DayOfWeek;
use App\Enum\Teacher\LocationType;
use App\Enum\Teacher\SessionDuration;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;
use App\State\Provider\Teacher\TeacherProfileEditProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TeacherProfileInput, TeacherProfileOutput>
 */
readonly class TeacherProfileEditProcessor implements ProcessorInterface
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
        /** @var TeacherProfileInput $data */
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->getTeacherProfile();

        if (!$profile) {
            throw new NotFoundHttpException('Profil professeur non trouvé');
        }

        $this->updateProfile($profile, $data);

        return $this->outputProvider->buildFromEntity($profile);
    }

    private function updateProfile(TeacherProfile $profile, TeacherProfileInput $data): void
    {
        // Description
        if ($data->description !== null) {
            $profile->description = $data->description;
        }

        // Years of experience
        if ($data->yearsOfExperience !== null) {
            $profile->yearsOfExperience = $data->yearsOfExperience;
        }

        // Student levels
        if (!empty($data->studentLevels) || $data->studentLevels === []) {
            $profile->studentLevels = $data->studentLevels;
        }

        // Age groups
        if (!empty($data->ageGroups) || $data->ageGroups === []) {
            $profile->ageGroups = $data->ageGroups;
        }

        // Course title
        if ($data->courseTitle !== null) {
            $profile->courseTitle = $data->courseTitle;
        }

        // Trial lesson
        $profile->offersTrial = $data->offersTrial;
        $profile->trialPrice = $data->trialPrice;

        // Locations
        foreach ($profile->locations->toArray() as $location) {
            $profile->removeLocation($location);
        }

        foreach ($data->locations as $locationInput) {
            $locationType = LocationType::tryFrom($locationInput->type ?? '');
            if (!$locationType) {
                continue;
            }

            $location = new TeacherProfileLocation();
            $location->type = $locationType;
            $location->address = $locationInput->address;
            $location->city = $locationInput->city;
            $location->country = $locationInput->country;
            $location->latitude = $locationInput->latitude !== null ? (string) $locationInput->latitude : null;
            $location->longitude = $locationInput->longitude !== null ? (string) $locationInput->longitude : null;
            $location->radius = $locationInput->radius;
            $profile->addLocation($location);
        }

        // Instruments (if provided)
        if (!empty($data->instrumentIds) || $data->instrumentIds === []) {
            // Remove existing instruments and flush first to avoid unique constraint violation
            foreach ($profile->instruments->toArray() as $instrument) {
                $profile->removeInstrument($instrument);
            }
            $this->entityManager->flush();

            foreach ($data->instrumentIds as $instrumentId) {
                $instrument = $this->instrumentRepository->find($instrumentId);
                if (!$instrument) {
                    continue;
                }

                $profileInstrument = new TeacherProfileInstrument();
                $profileInstrument->instrument = $instrument;
                $profile->addInstrument($profileInstrument);
            }
        }

        // Styles (if provided)
        if (!empty($data->styleIds) || $data->styleIds === []) {
            foreach ($profile->styles->toArray() as $style) {
                $profile->removeStyle($style);
            }

            foreach ($data->styleIds as $styleId) {
                $style = $this->styleRepository->find($styleId);
                if ($style) {
                    $profile->addStyle($style);
                }
            }
        }

        // Pricing
        foreach ($profile->pricing->toArray() as $pricing) {
            $profile->removePricing($pricing);
        }
        $this->entityManager->flush();

        foreach ($data->pricing as $pricingInput) {
            $duration = SessionDuration::tryFrom($pricingInput->duration ?? '');
            if (!$duration || $pricingInput->price === null) {
                continue;
            }

            $pricing = new TeacherProfilePricing();
            $pricing->duration = $duration;
            $pricing->price = $pricingInput->price;
            $profile->addPricing($pricing);
        }

        // Availability
        foreach ($profile->availability->toArray() as $availability) {
            $profile->removeAvailability($availability);
        }

        foreach ($data->availability as $availabilityInput) {
            $dayOfWeek = DayOfWeek::tryFrom($availabilityInput->dayOfWeek ?? '');
            if (!$dayOfWeek || $availabilityInput->startTime === null || $availabilityInput->endTime === null) {
                continue;
            }

            $availability = new TeacherAvailability();
            $availability->dayOfWeek = $dayOfWeek;
            $availability->startTime = new DateTimeImmutable($availabilityInput->startTime);
            $availability->endTime = new DateTimeImmutable($availabilityInput->endTime);
            $profile->addAvailability($availability);
        }

        // Packages
        foreach ($profile->packages->toArray() as $package) {
            $profile->removePackage($package);
        }

        foreach ($data->packages as $packageInput) {
            if (empty($packageInput->title)) {
                continue;
            }

            $package = new TeacherProfilePackage();
            $package->title = $packageInput->title;
            $package->description = $packageInput->description;
            $package->sessionsCount = $packageInput->sessionsCount;
            $package->price = $packageInput->price ?? 0;
            $profile->addPackage($package);
        }

        // Social Links
        foreach ($profile->socialLinks->toArray() as $socialLink) {
            $profile->removeSocialLink($socialLink);
        }
        $this->entityManager->flush();

        foreach ($data->socialLinks as $socialLinkInput) {
            $platform = SocialPlatform::tryFrom($socialLinkInput->platform ?? '');
            if (!$platform || empty($socialLinkInput->url)) {
                continue;
            }

            $socialLink = new TeacherSocialLink();
            $socialLink->platform = $platform;
            $socialLink->url = $socialLinkInput->url;
            $profile->addSocialLink($socialLink);
        }

        $profile->updateDatetime = new DateTimeImmutable();
        $this->entityManager->flush();
    }
}
