<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Report\Report;
use Symfony\Contracts\EventDispatcher\Event;

class ReportCreatedEvent extends Event
{
    public function __construct(
        public readonly Report $report,
    ) {
    }
}
