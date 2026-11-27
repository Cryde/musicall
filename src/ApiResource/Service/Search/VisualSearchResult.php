<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Search;

use App\Enum\Service\ServiceKind;

/**
 * A member of a kind shown by its works on their search's card (#1411, #1412): photographers, videographers, graphic
 * designers and webmasters. What every card shows, the delivery time, and remote work where the kind offers it.
 */
abstract class VisualSearchResult extends SpecialitySearchResult
{
    public ?int $deliveryDays = null;

    /** Whether the member works remotely; null, so left out, for a kind never given that way (ServiceKind::worksRemotely()). */
    public ?bool $online = null;

    abstract public static function kind(): ServiceKind;
}
