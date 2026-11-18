<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Edit\MyServiceOffer;
use App\ApiResource\Service\Edit\MyServicePlace;
use App\ApiResource\Service\SoundEngineering\MySoundEngineering;
use App\Entity\Attribute\Style;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\SoundEngineeringDetails;
use App\Service\Builder\Teacher\TeacherProfileBuilder;
use App\Service\Builder\Teacher\TeacherProfileMediaResourceBuilder;

/** Fills every writable field: the PATCH merges its body into this, so a field missed here would be reset (#1327). */
readonly class MySoundEngineeringBuilder
{
    public function __construct(
        private ServiceViewBuilder $serviceViewBuilder,
        private TeacherProfileBuilder $teacherProfileBuilder,
        private TeacherProfileMediaResourceBuilder $mediaResourceBuilder,
    ) {
    }

    public function build(Service $service): MySoundEngineering
    {
        $resource = new MySoundEngineering();
        $resource->id = (string) $service->id;
        $resource->status = $service->status->value;
        $resource->headline = $service->headline;
        $resource->description = $service->description;
        $resource->yearsOfExperience = $service->yearsOfExperience;
        $resource->specialities = $service->specialities;
        $resource->styleIds = array_values(array_map(static fn (Style $style): string => (string) $style->id, $service->styles->toArray()));
        $resource->styles = $this->teacherProfileBuilder->buildStyles($service->styles->toArray());
        $resource->places = array_values(array_map($this->place(...), $service->places->toArray()));
        $resource->offers = array_map($this->offer(...), $service->offersByPrice());

        $details = SoundEngineeringDetails::of($service);
        $resource->turnaroundDays = $details->turnaroundDays;
        $resource->revisionsIncluded = $details->revisionsIncluded;
        $resource->formats = $details->formats;
        $resource->gear = $details->gear;

        $resource->media = $this->mediaResourceBuilder->buildList($service->media->toArray());
        $resource->beforeAfter = $this->serviceViewBuilder->beforeAfter($service);
        $resource->works = $this->serviceViewBuilder->works($service);

        return $resource;
    }

    private function place(ServicePlace $place): MyServicePlace
    {
        $resource = new MyServicePlace();
        $resource->type = $place->type->value;
        $resource->address = $place->address;
        $resource->city = $place->city;
        $resource->country = $place->country;
        $resource->latitude = $place->latitude !== null ? (float) $place->latitude : null;
        $resource->longitude = $place->longitude !== null ? (float) $place->longitude : null;
        $resource->radius = $place->radius;

        return $resource;
    }

    private function offer(ServiceOffer $offer): MyServiceOffer
    {
        $resource = new MyServiceOffer();
        $resource->id = $offer->id;
        $resource->unit = $offer->unit->value;
        $resource->title = $offer->title;
        $resource->description = $offer->description;
        $resource->price = $offer->price;

        return $resource;
    }
}
