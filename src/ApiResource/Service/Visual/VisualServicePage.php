<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Visual;

use App\ApiResource\Service\ServicePage;
use App\Enum\Service\ServiceKind;

/**
 * The page of a kind shown by its works (#1411, #1412): what every kind's page shows, and what it delivers and when. Its
 * works are a gallery for a photographer or a graphic designer, videos and credits for a videographer, sites for a
 * webmaster.
 */
abstract class VisualServicePage extends ServicePage
{
    public ?int $deliveryDays = null;

    public ?string $deliverable = null;

    abstract public static function kind(): ServiceKind;
}
