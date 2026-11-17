<?php

declare(strict_types=1);

namespace App\Service\Service;

use App\ApiResource\Service\SoundEngineering\MySoundEngineering;
use App\Entity\Service\Service;
use App\Entity\Service\SoundEngineeringDetails;
use DateTimeImmutable;

/** Writes a sound engineering service from its form (#1410), every field as merged by the PATCH. */
readonly class SoundEngineeringWriter
{
    public function __construct(private ServiceWriter $serviceWriter)
    {
    }

    public function write(Service $service, MySoundEngineering $form): void
    {
        $service->headline = ServiceWriter::blankToNull($form->headline);
        $service->description = ServiceWriter::blankToNull($form->description);
        $service->yearsOfExperience = $form->yearsOfExperience;
        $service->specialities = array_values($form->specialities);
        $this->serviceWriter->writeStyles($service, $form->styleIds);
        $this->serviceWriter->writePlaces($service, $form->places);
        $this->serviceWriter->writeOffers($service, $form->offers);

        $details = SoundEngineeringDetails::of($service);
        $details->turnaroundDays = $form->turnaroundDays;
        $details->revisionsIncluded = $form->revisionsIncluded;
        $details->formats = array_values($form->formats);
        $details->gear = array_values(array_filter(array_map(trim(...), $form->gear), static fn (string $tag): bool => $tag !== ''));
        $service->details = $details->toArray();

        $service->updateDatetime = new DateTimeImmutable();
    }
}
