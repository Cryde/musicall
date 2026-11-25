<?php

declare(strict_types=1);

namespace App\Service\Service;

use App\ApiResource\Service\Visual\MyVisualService;
use App\Entity\Service\DeliveryDetails;
use App\Entity\Service\Service;

/** Writes a service of a kind shown by its works from its form (#1411, #1412), every field as merged by the PATCH. */
readonly class VisualServiceWriter
{
    public function __construct(private ServiceWriter $serviceWriter)
    {
    }

    public function write(Service $service, MyVisualService $form): void
    {
        $this->serviceWriter->writeForm($service, $form);

        $details = DeliveryDetails::of($service);
        $details->deliveryDays = $form->deliveryDays;
        $details->deliverable = ServiceWriter::blankToNull($form->deliverable);
        $service->details = $details->toArray();
    }
}
