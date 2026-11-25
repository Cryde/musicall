<?php

declare(strict_types=1);

namespace App\Service\Builder\User;

use App\ApiResource\User\Profile\PublicProfileService;
use App\Entity\Service\Service;
use App\Locale\SiteUrl;

readonly class PublicProfileServiceBuilder
{
    public function __construct(private SiteUrl $siteUrl)
    {
    }

    public function build(Service $service): PublicProfileService
    {
        $dto = new PublicProfileService();
        $dto->id = (string) $service->id;
        $dto->kind = $service->kind->value;
        $dto->status = $service->status->value;
        $dto->headline = $service->headline;
        $dto->url = $this->siteUrl->of($service->kind->pagePath($service->user->username));
        $offer = $service->leadOffer();
        $dto->price = $offer?->price;
        $dto->priceUnit = $offer?->unit->value;
        $dto->priceQuantity = $offer?->quantity;

        return $dto;
    }
}
