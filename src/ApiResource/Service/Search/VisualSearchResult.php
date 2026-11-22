<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Search;

use App\Enum\Service\ServiceKind;

/** A photographer or a videographer on their search's card (#1411): what every card shows, and the delivery time. */
abstract class VisualSearchResult extends SpecialitySearchResult
{
    public ?int $deliveryDays = null;

    abstract public static function kind(): ServiceKind;
}
