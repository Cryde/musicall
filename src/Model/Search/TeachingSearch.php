<?php

declare(strict_types=1);

namespace App\Model\Search;

use App\Entity\Attribute\Instrument;
use App\Enum\Service\TeachingSearchOrder;
use App\Enum\Teacher\AgeGroup;
use App\Enum\Teacher\StudentLevel;

/** The teacher search (#1314): the shared service search, and the filters only teaching has. */
class TeachingSearch
{
    public ServiceSearch $service;
    public ?Instrument $instrument = null;
    public ?StudentLevel $level = null;
    public ?AgeGroup $ageGroup = null;
    /** Only the teachers whose trial lesson is free. */
    public bool $freeTrial = false;
    /** None: the nearest first when there is a point, the newest otherwise. */
    public ?TeachingSearchOrder $order = null;

    public function __construct()
    {
        $this->service = new ServiceSearch();
    }
}
