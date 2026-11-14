<?php

declare(strict_types=1);

namespace App\Service\Teacher;

use App\ApiResource\Teacher\Private\Input\Availability;
use App\ApiResource\Teacher\Private\Input\Location;
use App\ApiResource\Teacher\Private\Input\Package;
use App\ApiResource\Teacher\Private\Input\Pricing;
use App\ApiResource\Teacher\Private\Input\SocialLink;
use App\ApiResource\Teacher\Private\TeacherProfileInput;
use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\Teacher\TeacherAvailability;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfileInstrument;
use App\Entity\Teacher\TeacherProfileLocation;
use App\Entity\Teacher\TeacherProfilePackage;
use App\Entity\Teacher\TeacherProfilePricing;
use App\Entity\Teacher\TeacherSocialLink;
use App\Enum\SocialPlatform;
use App\Enum\Teacher\DayOfWeek;
use App\Enum\Teacher\LocationType;
use App\Enum\Teacher\SessionDuration;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Writes a teacher profile from the create and the edit request. A field left out (null) is not written, so an edit
 * keeps what is stored (#1327) and a new profile keeps its empty default.
 */
readonly class TeacherProfileWriter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InstrumentRepository $instrumentRepository,
        private StyleRepository $styleRepository,
        private TeacherLessonContentWriter $lessonContentWriter,
    ) {
    }

    public function write(TeacherProfile $profile, TeacherProfileInput $data): void
    {
        if ($data->description !== null) {
            $profile->description = $data->description;
        }
        if ($data->yearsOfExperience !== null) {
            $profile->yearsOfExperience = $data->yearsOfExperience;
        }
        if ($data->studentLevels !== null) {
            $profile->studentLevels = $data->studentLevels;
        }
        if ($data->ageGroups !== null) {
            $profile->ageGroups = $data->ageGroups;
        }
        if ($data->courseTitle !== null) {
            $profile->courseTitle = $data->courseTitle;
        }
        $this->writeTrial($profile, $data);

        $this->lessonContentWriter->write($profile, $data);

        if ($data->locations !== null) {
            $this->writeLocations($profile, $data->locations);
        }
        if ($data->instrumentIds !== null) {
            $this->writeInstruments($profile, $data->instrumentIds);
        }
        if ($data->styleIds !== null) {
            $this->writeStyles($profile, $data->styleIds);
        }
        if ($data->pricing !== null) {
            $this->writePricing($profile, $data->pricing);
        }
        if ($data->availability !== null) {
            $this->writeAvailability($profile, $data->availability);
        }
        if ($data->packages !== null) {
            $this->writePackages($profile, $data->packages);
        }
        if ($data->socialLinks !== null) {
            $this->writeSocialLinks($profile, $data->socialLinks);
        }

        $profile->updateDatetime = new DateTimeImmutable();
    }

    /** The price only means something next to the switch, so a null price clears it only when the switch is sent. */
    private function writeTrial(TeacherProfile $profile, TeacherProfileInput $data): void
    {
        if ($data->offersTrial !== null) {
            $profile->offersTrial = $data->offersTrial;
            $profile->trialPrice = $data->trialPrice;
        } elseif ($data->trialPrice !== null) {
            $profile->trialPrice = $data->trialPrice;
        }
    }

    /** @param Location[] $locations */
    private function writeLocations(TeacherProfile $profile, array $locations): void
    {
        foreach ($profile->locations->toArray() as $location) {
            $profile->removeLocation($location);
        }

        foreach ($locations as $locationInput) {
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
    }

    /** @param string[] $instrumentIds */
    private function writeInstruments(TeacherProfile $profile, array $instrumentIds): void
    {
        $this->flushRemovals($profile->instruments->toArray(), $profile->removeInstrument(...));

        foreach ($instrumentIds as $instrumentId) {
            $instrument = $this->instrumentRepository->find($instrumentId);
            if (!$instrument instanceof Instrument) {
                continue;
            }

            $profileInstrument = new TeacherProfileInstrument();
            $profileInstrument->instrument = $instrument;
            $profile->addInstrument($profileInstrument);
        }
    }

    /** @param string[] $styleIds */
    private function writeStyles(TeacherProfile $profile, array $styleIds): void
    {
        foreach ($profile->styles->toArray() as $style) {
            $profile->removeStyle($style);
        }

        foreach ($styleIds as $styleId) {
            $style = $this->styleRepository->find($styleId);
            if ($style instanceof Style) {
                $profile->addStyle($style);
            }
        }
    }

    /** @param Pricing[] $pricingInputs */
    private function writePricing(TeacherProfile $profile, array $pricingInputs): void
    {
        $this->flushRemovals($profile->pricing->toArray(), $profile->removePricing(...));

        foreach ($pricingInputs as $pricingInput) {
            $duration = SessionDuration::tryFrom($pricingInput->duration ?? '');
            if (!$duration || $pricingInput->price === null) {
                continue;
            }

            $pricing = new TeacherProfilePricing();
            $pricing->duration = $duration;
            $pricing->price = $pricingInput->price;
            $profile->addPricing($pricing);
        }
    }

    /** @param Availability[] $availabilityInputs */
    private function writeAvailability(TeacherProfile $profile, array $availabilityInputs): void
    {
        foreach ($profile->availability->toArray() as $availability) {
            $profile->removeAvailability($availability);
        }

        foreach ($availabilityInputs as $availabilityInput) {
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
    }

    /** @param Package[] $packageInputs */
    private function writePackages(TeacherProfile $profile, array $packageInputs): void
    {
        foreach ($profile->packages->toArray() as $package) {
            $profile->removePackage($package);
        }

        foreach ($packageInputs as $packageInput) {
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
    }

    /** @param SocialLink[] $socialLinkInputs */
    private function writeSocialLinks(TeacherProfile $profile, array $socialLinkInputs): void
    {
        $this->flushRemovals($profile->socialLinks->toArray(), $profile->removeSocialLink(...));

        foreach ($socialLinkInputs as $socialLinkInput) {
            $platform = SocialPlatform::tryFrom($socialLinkInput->platform ?? '');
            if (!$platform || empty($socialLinkInput->url)) {
                continue;
            }

            $socialLink = new TeacherSocialLink();
            $socialLink->platform = $platform;
            $socialLink->url = $socialLinkInput->url;
            $profile->addSocialLink($socialLink);
        }
    }

    /**
     * Deletes the stored rows before the new ones are inserted: re-adding the same instrument, duration or platform
     * would otherwise hit the unique key.
     *
     * @template T of object
     *
     * @param array<T>           $stored
     * @param callable(T): mixed $remove
     */
    private function flushRemovals(array $stored, callable $remove): void
    {
        if ($stored === []) {
            return;
        }
        foreach ($stored as $item) {
            $remove($item);
        }
        $this->entityManager->flush();
    }
}
