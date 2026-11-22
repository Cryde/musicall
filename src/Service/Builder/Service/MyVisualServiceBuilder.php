<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Visual\MyVisualService;
use App\Entity\Service\DeliveryDetails;
use App\Entity\Service\Service;

readonly class MyVisualServiceBuilder
{
    public function __construct(private MyServiceFormBuilder $formBuilder)
    {
    }

    /**
     * @template T of MyVisualService
     *
     * @param T $resource the kind's own resource
     *
     * @return T
     */
    public function build(Service $service, MyVisualService $resource): MyVisualService
    {
        $this->formBuilder->fill($resource, $service);

        $details = DeliveryDetails::of($service);
        $resource->deliveryDays = $details->deliveryDays;
        $resource->deliverable = $details->deliverable;

        return $resource;
    }
}
