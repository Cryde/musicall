<?php

declare(strict_types=1);

namespace App\Service\Service;

use App\ApiResource\Service\Edit\MyServiceOffer;
use App\ApiResource\Service\Edit\MyServicePlace;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServicePlaceType;
use App\Repository\Attribute\StyleRepository;

/** Writes the lists every kind shares, each replaced whole by what the form sent. */
readonly class ServiceWriter
{
    public function __construct(private StyleRepository $styleRepository)
    {
    }

    /** Blank is nothing said, so a headline of spaces never counts as filled in. */
    public static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    /** @param string[] $styleIds an unknown id is skipped */
    public function writeStyles(Service $service, array $styleIds): void
    {
        foreach ($service->styles->toArray() as $style) {
            $service->removeStyle($style);
        }

        foreach ($this->styleRepository->findBy(['id' => array_values($styleIds)]) as $style) {
            $service->addStyle($style);
        }
    }

    /**
     * Rewritten only when they differ from what is stored, so a save that left them alone touches no row.
     *
     * @param MyServicePlace[] $places already validated
     */
    public function writePlaces(Service $service, array $places): void
    {
        $sent = array_map(static fn (MyServicePlace $place): array => [
            $place->type, $place->address, $place->city, $place->country,
            $place->latitude !== null ? (string) $place->latitude : null,
            $place->longitude !== null ? (string) $place->longitude : null,
            $place->type === ServicePlaceType::AtClientPlace->value ? $place->radius : null,
        ], $places);
        $stored = array_map(static fn (ServicePlace $place): array => [
            $place->type->value, $place->address, $place->city, $place->country, $place->latitude, $place->longitude, $place->radius,
        ], $service->places->toArray());
        if (self::sameRows($sent, $stored)) {
            return;
        }

        foreach ($service->places->toArray() as $place) {
            $service->removePlace($place);
        }

        foreach ($places as $input) {
            $place = new ServicePlace();
            $place->type = ServicePlaceType::from((string) $input->type);
            $place->address = $input->address;
            $place->city = $input->city;
            $place->country = $input->country;
            $place->latitude = $input->latitude !== null ? (string) $input->latitude : null;
            $place->longitude = $input->longitude !== null ? (string) $input->longitude : null;
            // Only the member's travel has a reach of its own.
            $place->radius = $place->type === ServicePlaceType::AtClientPlace ? $input->radius : null;
            $service->addPlace($place);
        }
    }

    /**
     * Rewritten only when they differ from what is stored, so an offer's id stays while the offer does.
     *
     * @param MyServiceOffer[] $offers already validated against the kind's units
     */
    public function writeOffers(Service $service, array $offers): void
    {
        $sent = array_map(static fn (MyServiceOffer $offer): array => [$offer->unit, $offer->title, $offer->description, $offer->price], $offers);
        $stored = array_map(static fn (ServiceOffer $offer): array => [$offer->unit->value, $offer->title, $offer->description, $offer->price], $service->offers->toArray());
        if (self::sameRows($sent, $stored)) {
            return;
        }

        foreach ($service->offers->toArray() as $offer) {
            $service->removeOffer($offer);
        }

        foreach ($offers as $input) {
            $offer = new ServiceOffer();
            $offer->unit = OfferUnit::from((string) $input->unit);
            $offer->title = $input->title;
            $offer->description = $input->description;
            $offer->price = (int) $input->price;
            $service->addOffer($offer);
        }
    }

    /**
     * @param array<array<mixed>> $sent
     * @param array<array<mixed>> $stored
     */
    private static function sameRows(array $sent, array $stored): bool
    {
        sort($sent);
        sort($stored);

        return $sent === $stored;
    }
}
