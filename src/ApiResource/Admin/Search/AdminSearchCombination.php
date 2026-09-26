<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Search;

/** One (type, instrument, city) the filters were searched with; null where the search left it open. */
class AdminSearchCombination
{
    public ?int $type = null;
    public ?string $instrumentName = null;
    public ?string $locationName = null;
    public int $searches = 0;
    /** Different people, counted per day: the visitor hash changes every day. */
    public int $visitors = 0;
    public int $zeroResults = 0;
}
