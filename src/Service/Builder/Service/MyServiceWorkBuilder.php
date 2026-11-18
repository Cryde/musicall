<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Work\MyServiceWork;
use App\ApiResource\Service\Work\MyServiceWorkImage;
use App\Entity\Service\ServiceWork;
use App\Entity\Service\ServiceWorkImage;

/** Fills every writable field: the PATCH merges its body into this, so a field missed here would be reset (#1327). */
readonly class MyServiceWorkBuilder
{
    public function __construct(private ServiceViewBuilder $viewBuilder)
    {
    }

    public function build(ServiceWork $work): MyServiceWork
    {
        $resource = $this->viewBuilder->work($work, new MyServiceWork());
        $resource->serviceId = (string) $work->service->id;
        $resource->mediaUrl = $work->mediaUrl;

        return $resource;
    }

    public function image(ServiceWorkImage $image): MyServiceWorkImage
    {
        $resource = $this->viewBuilder->workImage($image, new MyServiceWorkImage());
        $resource->serviceId = (string) $image->work->service->id;
        $resource->workId = (string) $image->work->id;

        return $resource;
    }
}
