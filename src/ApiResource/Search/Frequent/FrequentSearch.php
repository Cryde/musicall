<?php

declare(strict_types=1);

namespace App\ApiResource\Search\Frequent;

/** One frequent search: enough for the homepage to label it and link into the search with it. */
class FrequentSearch
{
    /** The announce type it searched for: 1 a band's announce, 2 a musician's. */
    public int $type;
    public string $instrumentId;
    public string $instrumentName;
    public string $locationName;
    public float $latitude;
    public float $longitude;
}
