<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\MyService;
use App\Entity\Service\Service;
use App\Enum\Service\ServicePart;

class MyServiceBuilder
{
    public function build(Service $service): MyService
    {
        $resource = new MyService();
        $resource->id = (string) $service->id;
        $resource->kind = $service->kind->value;
        $resource->headline = $service->headline;
        $resource->status = $service->status->value;
        $resource->completeness = $service->completeness();
        $resource->missingToPublish = array_map(static fn (ServicePart $part): string => $part->value, $service->missingToPublish());

        return $resource;
    }
}
