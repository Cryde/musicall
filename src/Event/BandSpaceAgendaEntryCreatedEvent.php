<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\AgendaEntry;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class BandSpaceAgendaEntryCreatedEvent extends Event
{
    public function __construct(
        public readonly AgendaEntry $entry,
        public readonly User $actor,
    ) {
    }
}
