<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Edit\MyServiceForm;
use App\ApiResource\Service\Edit\MyServiceOffer;
use App\ApiResource\Service\Edit\MyServicePlace;
use App\Entity\Attribute\Style;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Service\Builder\Teacher\TeacherProfileBuilder;
use App\Service\Builder\Teacher\TeacherProfileMediaResourceBuilder;

/**
 * What every kind's form shows (#1410, #1411). Fills every writable field: the PATCH merges its body into this, so a
 * field missed here would be reset (#1327).
 */
readonly class MyServiceFormBuilder
{
    public function __construct(
        private ServiceViewBuilder $serviceViewBuilder,
        private TeacherProfileBuilder $teacherProfileBuilder,
        private TeacherProfileMediaResourceBuilder $mediaResourceBuilder,
    ) {
    }

    public function fill(MyServiceForm $form, Service $service): void
    {
        $form->id = (string) $service->id;
        $form->status = $service->status->value;
        $form->headline = $service->headline;
        $form->description = $service->description;
        $form->experienceStartYear = $service->experienceStartYear;
        $form->specialities = $service->specialities;
        $form->styleIds = array_values(array_map(static fn (Style $style): string => (string) $style->id, $service->styles->toArray()));
        $form->styles = $this->teacherProfileBuilder->buildStyles($service->styles->toArray());
        $form->places = $this->places($service);
        $form->offers = $this->offers($service);
        $form->media = $this->mediaResourceBuilder->buildList($service->media->toArray());
        $form->works = $this->serviceViewBuilder->works($service);
    }

    /** @return list<MyServicePlace> as its owner edits them: with the address the page never shows */
    private function places(Service $service): array
    {
        return array_values(array_map(static function (ServicePlace $place): MyServicePlace {
            $resource = new MyServicePlace();
            $resource->type = $place->type->value;
            $resource->address = $place->address;
            $resource->city = $place->city;
            $resource->country = $place->country;
            $resource->latitude = $place->latitude !== null ? (float) $place->latitude : null;
            $resource->longitude = $place->longitude !== null ? (float) $place->longitude : null;
            $resource->radius = $place->radius;

            return $resource;
        }, $service->places->toArray()));
    }

    /** @return list<MyServiceOffer> as its owner edits them, the cheapest first */
    private function offers(Service $service): array
    {
        return array_map(static function (ServiceOffer $offer): MyServiceOffer {
            $resource = new MyServiceOffer();
            $resource->id = $offer->id;
            $resource->unit = $offer->unit->value;
            $resource->title = $offer->title;
            $resource->description = $offer->description;
            $resource->price = $offer->price;

            return $resource;
        }, $service->offersByPrice());
    }
}
