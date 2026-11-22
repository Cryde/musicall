<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Visual\VisualServicePage;
use App\Entity\Service\DeliveryDetails;
use App\Entity\Service\Service;

readonly class VisualServicePageBuilder
{
    public function __construct(private ServicePageBuilder $pageBuilder)
    {
    }

    /**
     * @template T of VisualServicePage
     *
     * @param T $page the kind's own resource
     *
     * @return T
     */
    public function build(Service $service, VisualServicePage $page): VisualServicePage
    {
        $this->pageBuilder->fill($page, $service);

        $details = DeliveryDetails::of($service);
        $page->deliveryDays = $details->deliveryDays;
        $page->deliverable = $details->deliverable;

        return $page;
    }
}
