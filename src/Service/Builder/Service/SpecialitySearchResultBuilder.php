<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Search\SpecialitySearchResult;
use App\Entity\Service\Service;
use App\Service\Builder\Teacher\TeacherProfileBuilder;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;

/** What every card of a search by speciality shows (#1410, #1411): each kind's builder adds its details. */
readonly class SpecialitySearchResultBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $pictureUrlBuilder,
        private TeacherProfileBuilder $teacherProfileBuilder,
        private ServiceViewBuilder $serviceViewBuilder,
    ) {
    }

    /** @param float|null $distance in metres */
    public function fill(SpecialitySearchResult $result, Service $service, ?float $distance, ?string $searchedSpeciality): void
    {
        $user = $service->user;
        $result->username = $user->username;
        $result->name = $user->getDisplayName();
        $result->profilePictureUrl = $this->pictureUrlBuilder->build($user);
        $result->headline = $service->headline;
        $specialities = $service->specialities;
        // The searched one first, as « Mastering et mixage » reads.
        usort($specialities, static fn (string $a, string $b): int => ($b === $searchedSpeciality) <=> ($a === $searchedSpeciality));
        $result->specialities = $specialities;
        $result->distance = $distance !== null ? round($distance / 1000, 1) : null;

        $offer = $service->leadOffer();
        $result->price = $offer?->price;
        $result->priceUnit = $offer?->unit->value;
        $result->styles = $this->teacherProfileBuilder->buildStyles($service->styles->toArray());
        $result->media = $this->serviceViewBuilder->firstMedia($service);
    }
}
