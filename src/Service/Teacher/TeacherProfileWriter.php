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
use App\Entity\Service\Service;
use App\Entity\Service\ServiceAvailability;
use App\Entity\Service\ServiceInstrument;
use App\Entity\Service\ServiceLink;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\OfferUnit;
use App\Enum\SocialPlatform;
use App\Enum\Teacher\DayOfWeek;
use App\Enum\Teacher\LocationType;
use App\Enum\Teacher\SessionDuration;
use App\Repository\Attribute\InstrumentRepository;
use App\Service\Service\ServiceWriter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Writes a teaching service from the teacher profile's create and edit request. A field left out (null) is not
 * written, so an edit keeps what is stored (#1327) and a new profile keeps its empty default.
 */
readonly class TeacherProfileWriter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InstrumentRepository $instrumentRepository,
        private TeacherLessonContentWriter $lessonContentWriter,
        private ServiceWriter $serviceWriter,
    ) {
    }

    public function write(Service $profile, TeacherProfileInput $data): void
    {
        if ($data->description !== null) {
            $profile->description = $data->description;
        }
        if ($data->yearsOfExperience !== null) {
            $profile->yearsOfExperience = $data->yearsOfExperience;
        }
        if ($data->courseTitle !== null) {
            $profile->headline = $data->courseTitle;
        }
        $this->writeDetails($profile, $data);

        if ($data->locations !== null) {
            $this->writeLocations($profile, $data->locations);
        }
        if ($data->instrumentIds !== null) {
            $this->writeInstruments($profile, $data->instrumentIds);
        }
        if ($data->styleIds !== null) {
            $this->serviceWriter->writeStyles($profile, $data->styleIds);
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

    private function writeDetails(Service $profile, TeacherProfileInput $data): void
    {
        $details = TeachingDetails::of($profile);
        if ($data->studentLevels !== null) {
            $details->studentLevels = $data->studentLevels;
        }
        if ($data->ageGroups !== null) {
            $details->ageGroups = $data->ageGroups;
        }
        $this->writeTrial($details, $data);
        $this->lessonContentWriter->write($details, $data);
        $profile->details = $details->toArray();
    }

    /** The price only means something next to the switch, so a null price clears it only when the switch is sent. */
    private function writeTrial(TeachingDetails $details, TeacherProfileInput $data): void
    {
        if ($data->offersTrial !== null) {
            $details->offersTrial = $data->offersTrial;
            $details->trialPrice = $data->trialPrice;
        } elseif ($data->trialPrice !== null) {
            $details->trialPrice = $data->trialPrice;
        }
    }

    /** @param Location[] $locations */
    private function writeLocations(Service $profile, array $locations): void
    {
        foreach ($profile->places->toArray() as $place) {
            $profile->removePlace($place);
        }

        foreach ($locations as $locationInput) {
            $locationType = LocationType::tryFrom($locationInput->type ?? '');
            if (!$locationType) {
                continue;
            }

            $place = new ServicePlace();
            $place->type = $locationType->placeType();
            $place->address = $locationInput->address;
            $place->city = $locationInput->city;
            $place->country = $locationInput->country;
            $place->latitude = $locationInput->latitude !== null ? (string) $locationInput->latitude : null;
            $place->longitude = $locationInput->longitude !== null ? (string) $locationInput->longitude : null;
            $place->radius = $locationInput->radius;
            $profile->addPlace($place);
        }
    }

    /** @param string[] $instrumentIds */
    private function writeInstruments(Service $profile, array $instrumentIds): void
    {
        $this->flushRemovals($profile->instruments->toArray(), $profile->removeInstrument(...));

        foreach ($instrumentIds as $instrumentId) {
            $instrument = $this->instrumentRepository->find($instrumentId);
            if (!$instrument instanceof Instrument) {
                continue;
            }

            $profileInstrument = new ServiceInstrument();
            $profileInstrument->instrument = $instrument;
            $profile->addInstrument($profileInstrument);
        }
    }

    /** @param Pricing[] $pricingInputs */
    private function writePricing(Service $profile, array $pricingInputs): void
    {
        foreach ($profile->offersIn(OfferUnit::Lesson) as $offer) {
            $profile->removeOffer($offer);
        }

        // One price per lesson length: the first one sent wins, as the unique key the pricing table had allowed one.
        $priced = [];
        foreach ($pricingInputs as $pricingInput) {
            $duration = SessionDuration::tryFrom($pricingInput->duration ?? '');
            if (!$duration || $pricingInput->price === null || isset($priced[$duration->value])) {
                continue;
            }
            $priced[$duration->value] = true;

            $offer = new ServiceOffer();
            $offer->unit = OfferUnit::Lesson;
            $offer->quantity = $duration->getMinutes();
            $offer->price = $pricingInput->price;
            $profile->addOffer($offer);
        }
    }

    /** @param Availability[] $availabilityInputs */
    private function writeAvailability(Service $profile, array $availabilityInputs): void
    {
        foreach ($profile->availability->toArray() as $availability) {
            $profile->removeAvailability($availability);
        }

        foreach ($availabilityInputs as $availabilityInput) {
            $dayOfWeek = DayOfWeek::tryFrom($availabilityInput->dayOfWeek ?? '');
            if (!$dayOfWeek || $availabilityInput->startTime === null || $availabilityInput->endTime === null) {
                continue;
            }

            $availability = new ServiceAvailability();
            $availability->dayOfWeek = $dayOfWeek;
            $availability->startTime = new DateTimeImmutable($availabilityInput->startTime);
            $availability->endTime = new DateTimeImmutable($availabilityInput->endTime);
            $profile->addAvailability($availability);
        }
    }

    /** @param Package[] $packageInputs */
    private function writePackages(Service $profile, array $packageInputs): void
    {
        foreach ($profile->offersIn(OfferUnit::Package) as $offer) {
            $profile->removeOffer($offer);
        }

        foreach ($packageInputs as $packageInput) {
            if (empty($packageInput->title)) {
                continue;
            }

            $offer = new ServiceOffer();
            $offer->unit = OfferUnit::Package;
            $offer->title = $packageInput->title;
            $offer->description = $packageInput->description;
            $offer->quantity = $packageInput->sessionsCount;
            $offer->price = $packageInput->price ?? 0;
            $profile->addOffer($offer);
        }
    }

    /** @param SocialLink[] $socialLinkInputs */
    private function writeSocialLinks(Service $profile, array $socialLinkInputs): void
    {
        $this->flushRemovals($profile->links->toArray(), $profile->removeLink(...));

        foreach ($socialLinkInputs as $socialLinkInput) {
            $platform = SocialPlatform::tryFrom($socialLinkInput->platform ?? '');
            if (!$platform || empty($socialLinkInput->url)) {
                continue;
            }

            $link = new ServiceLink();
            $link->platform = $platform;
            $link->url = $socialLinkInput->url;
            $profile->addLink($link);
        }
    }

    /**
     * Deletes the stored rows before the new ones are inserted: re-adding the same instrument or platform would
     * otherwise hit the unique key.
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
