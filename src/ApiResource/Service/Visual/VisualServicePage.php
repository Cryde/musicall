<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Visual;

use App\ApiResource\Service\ServicePage;
use App\Enum\Service\ServiceKind;

/**
 * A photographer's or a videographer's page (#1411): what every kind's page shows, and what it delivers and when. Its
 * works are a gallery for a photographer, videos and credits for a videographer.
 */
abstract class VisualServicePage extends ServicePage
{
    public ?int $deliveryDays = null;

    public ?string $deliverable = null;

    abstract public static function kind(): ServiceKind;
}
