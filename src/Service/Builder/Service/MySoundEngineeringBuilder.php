<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\SoundEngineering\MySoundEngineering;
use App\Entity\Service\Service;
use App\Entity\Service\SoundEngineeringDetails;

readonly class MySoundEngineeringBuilder
{
    public function __construct(
        private MyServiceFormBuilder $formBuilder,
        private ServiceViewBuilder $serviceViewBuilder,
    ) {
    }

    public function build(Service $service): MySoundEngineering
    {
        $resource = new MySoundEngineering();
        $this->formBuilder->fill($resource, $service);

        $details = SoundEngineeringDetails::of($service);
        $resource->turnaroundDays = $details->turnaroundDays;
        $resource->revisionsIncluded = $details->revisionsIncluded;
        $resource->formats = $details->formats;
        $resource->gear = $details->gear;
        $resource->beforeAfter = $this->serviceViewBuilder->beforeAfter($service);

        return $resource;
    }
}
