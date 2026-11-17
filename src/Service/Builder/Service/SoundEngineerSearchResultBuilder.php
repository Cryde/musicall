<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Search\SoundEngineerSearchResult;
use App\Entity\Service\Service;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\SoundEngineeringDetails;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServicePlaceType;
use App\Enum\Service\SoundEngineeringSpeciality;
use App\Service\Builder\Teacher\TeacherProfileBuilder;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;

readonly class SoundEngineerSearchResultBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $pictureUrlBuilder,
        private TeacherProfileBuilder $teacherProfileBuilder,
        private ServiceViewBuilder $serviceViewBuilder,
    ) {
    }

    /** @param float|null $distance in metres */
    public function build(Service $service, ?float $distance, ?SoundEngineeringSpeciality $searched): SoundEngineerSearchResult
    {
        $user = $service->user;
        $result = new SoundEngineerSearchResult();
        $result->username = $user->username;
        $result->name = $user->getDisplayName();
        $result->profilePictureUrl = $this->pictureUrlBuilder->build($user);
        $result->headline = $service->headline;
        $result->specialities = $this->specialities($service, $searched);
        $result->distance = $distance !== null ? round($distance / 1000, 1) : null;
        $result->online = $service->places->exists(static fn (int $key, ServicePlace $place): bool => $place->type === ServicePlaceType::Remote);

        $offer = $service->headlineOffer(OfferUnit::Track);
        $result->price = $offer?->price;
        $result->priceUnit = $offer?->unit->value;
        $result->turnaroundDays = SoundEngineeringDetails::of($service)->turnaroundDays;
        $result->styles = $this->teacherProfileBuilder->buildStyles($service->styles->toArray());
        $result->media = $this->serviceViewBuilder->firstMedia($service);

        return $result;
    }

    /** @return list<string> the searched one first, as « Mastering et mixage » reads */
    private function specialities(Service $service, ?SoundEngineeringSpeciality $searched): array
    {
        $specialities = $service->specialities;
        usort($specialities, static fn (string $a, string $b): int => ($b === $searched?->value) <=> ($a === $searched?->value));

        return $specialities;
    }
}
