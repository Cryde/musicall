<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Search\SoundEngineerSearchResult;
use App\Entity\Service\Service;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\SoundEngineeringDetails;
use App\Enum\Service\ServicePlaceType;

readonly class SoundEngineerSearchResultBuilder
{
    public function __construct(private SpecialitySearchResultBuilder $cardBuilder)
    {
    }

    /** @param float|null $distance in metres */
    public function build(Service $service, ?float $distance, ?string $searchedSpeciality): SoundEngineerSearchResult
    {
        $result = new SoundEngineerSearchResult();
        $this->cardBuilder->fill($result, $service, $distance, $searchedSpeciality);
        $result->online = $service->places->exists(static fn (int $key, ServicePlace $place): bool => $place->type === ServicePlaceType::Remote);
        $result->turnaroundDays = SoundEngineeringDetails::of($service)->turnaroundDays;

        return $result;
    }
}
