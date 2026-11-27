<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Search\VisualSearchResult;
use App\Entity\Service\DeliveryDetails;
use App\Entity\Service\Service;

readonly class VisualSearchResultBuilder
{
    public function __construct(private SpecialitySearchResultBuilder $cardBuilder)
    {
    }

    /**
     * @template T of VisualSearchResult
     *
     * @param T          $result   the kind's own resource
     * @param float|null $distance in metres
     *
     * @return T
     */
    public function build(Service $service, VisualSearchResult $result, ?float $distance, ?string $searchedSpeciality): VisualSearchResult
    {
        $this->cardBuilder->fill($result, $service, $distance, $searchedSpeciality);
        $result->deliveryDays = DeliveryDetails::of($service)->deliveryDays;
        // Its places are only loaded for a kind given remotely (ServiceRepository::findForSearchCards()).
        $result->online = $service->kind->worksRemotely() ? $service->isOfferedRemotely() : null;

        return $result;
    }
}
