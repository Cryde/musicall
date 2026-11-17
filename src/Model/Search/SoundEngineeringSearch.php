<?php

declare(strict_types=1);

namespace App\Model\Search;

use App\Entity\Attribute\Style;
use App\Enum\Service\SoundEngineeringSpeciality;

/** The sound engineer search (#1410): the shared service search, and the filters only this kind has. */
class SoundEngineeringSearch
{
    public ServiceSearch $service;
    public ?SoundEngineeringSpeciality $speciality = null;
    public ?Style $style = null;

    public function __construct()
    {
        $this->service = new ServiceSearch();
    }
}
