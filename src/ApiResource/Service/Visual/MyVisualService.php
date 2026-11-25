<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Visual;

use App\ApiResource\Service\Edit\MyServiceForm;
use App\Entity\Service\DeliveryDetails;
use Symfony\Component\Validator\Constraints as Assert;

/** A photography or video service's form (#1411): what every kind's form edits, and what it delivers and when. */
abstract class MyVisualService extends MyServiceForm
{
    /** « sous 7 jours ». */
    #[Assert\Range(notInRangeMessage: 'service.delivery_days_invalid', min: 1, max: 365)]
    public ?int $deliveryDays = null;

    /** « 40 photos retouchées ». */
    #[Assert\Length(max: DeliveryDetails::MAX_DELIVERABLE_LENGTH)]
    public ?string $deliverable = null;
}
