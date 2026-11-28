<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Search\ProducerSearchResult;
use App\Entity\Service\Service;

readonly class ProducerSearchResultBuilder
{
    public function __construct(private SpecialitySearchResultBuilder $cardBuilder)
    {
    }

    /** @param float|null $distance in metres */
    public function build(Service $service, ?float $distance, ?string $searchedSpeciality): ProducerSearchResult
    {
        $result = new ProducerSearchResult();
        $this->cardBuilder->fill($result, $service, $distance, $searchedSpeciality);
        $result->online = $service->isOfferedRemotely();

        return $result;
    }
}
