<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\FinanceEntrySplit;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class BandSpaceFinanceSplitAssignedEvent extends Event
{
    public function __construct(
        public readonly FinanceEntrySplit $split,
        public readonly User $actor,
    ) {
    }
}
