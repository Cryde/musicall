<?php

namespace App\Model\Search;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;

class MusicianSearch
{
    public int $type;
    public Instrument $instrument;
    /** @var Style[] */
    public array $styles = [];
    public ?float $latitude = null;
    public ?float $longitude = null;

}
