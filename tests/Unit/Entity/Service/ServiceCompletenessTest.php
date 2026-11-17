<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Service;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceAvailability;
use App\Entity\Service\ServiceInstrument;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServicePart;
use App\Enum\Service\ServicePlaceType;
use App\Enum\Teacher\DayOfWeek;
use PHPUnit\Framework\TestCase;

class ServiceCompletenessTest extends TestCase
{
    public function test_an_empty_teaching_service_misses_everything_it_needs_to_be_published(): void
    {
        $service = $this->teaching();

        $this->assertSame(0, $service->completeness());
        $this->assertSame(
            [ServicePart::Headline, ServicePart::Description, ServicePart::Places, ServicePart::Offers],
            $service->missingToPublish(),
        );
    }

    public function test_a_blank_headline_or_description_is_not_filled_in(): void
    {
        $service = $this->teaching();
        $service->headline = '   ';
        $service->description = "\n";

        $this->assertSame(0, $service->completeness());
        $this->assertContains(ServicePart::Headline, $service->missingToPublish());
        $this->assertContains(ServicePart::Description, $service->missingToPublish());
    }

    public function test_a_teaching_service_with_a_headline_a_description_a_place_and_an_offer_can_be_published(): void
    {
        $service = $this->teaching();
        $service->headline = 'Cours de guitare';
        $service->description = 'Du blues au jazz';
        $place = new ServicePlace();
        $place->type = ServicePlaceType::Remote;
        $service->addPlace($place);
        $offer = new ServiceOffer();
        $offer->unit = OfferUnit::Package;
        $offer->price = 10000;
        $service->addOffer($offer);

        $this->assertSame([], $service->missingToPublish());
        $this->assertSame(40, $service->completeness());
    }

    public function test_a_teaching_service_with_every_part_filled_in_is_complete(): void
    {
        $service = $this->teaching();
        $service->headline = 'Cours de guitare';
        $service->description = 'Du blues au jazz';
        $service->addInstrument(new ServiceInstrument());
        $details = new TeachingDetails();
        $details->studentLevels = ['beginner'];
        $details->ageGroups = ['adults'];
        $details->levelGoals = ['beginner' => ['title' => 'Les bases', 'points' => []]];
        $details->songs = [['artist' => null, 'title' => 'Hey Joe']];
        $service->details = $details->toArray();
        $place = new ServicePlace();
        $place->type = ServicePlaceType::AtMyPlace;
        $service->addPlace($place);
        $slot = new ServiceAvailability();
        $slot->dayOfWeek = DayOfWeek::MONDAY;
        $service->addAvailability($slot);
        $offer = new ServiceOffer();
        $offer->unit = OfferUnit::Lesson;
        $offer->price = 3000;
        $service->addOffer($offer);

        $this->assertSame(100, $service->completeness());
    }

    /** The goals of a level no longer taught are not shown, so they fill nothing in. */
    public function test_goals_of_a_level_no_longer_taught_do_not_count(): void
    {
        $service = $this->teaching();
        $details = new TeachingDetails();
        $details->studentLevels = ['advanced'];
        $details->levelGoals = ['beginner' => ['title' => 'Les bases', 'points' => []]];
        $service->details = $details->toArray();

        $this->assertFalse(ServicePart::LevelGoals->isFilledIn($service));
        $this->assertTrue(ServicePart::StudentLevels->isFilledIn($service));
    }

    private function teaching(): Service
    {
        $service = new Service();
        $service->kind = ServiceKind::Teaching;

        return $service;
    }
}
