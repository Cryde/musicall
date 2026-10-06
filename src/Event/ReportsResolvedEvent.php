<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Report\Report;
use App\Entity\User;
use App\Enum\Report\ReportOutcome;
use Symfony\Contracts\EventDispatcher\Event;

/** Every report a moderator closed with one decision on one target. */
class ReportsResolvedEvent extends Event
{
    /**
     * @param list<Report> $reports
     */
    public function __construct(
        public readonly array $reports,
        public readonly ReportOutcome $outcome,
        public readonly User $moderator,
    ) {
    }
}
