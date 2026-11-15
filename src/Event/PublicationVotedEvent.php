<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Publication;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/** A publication received an up vote. The voter is null for an anonymous vote. */
class PublicationVotedEvent extends Event
{
    public function __construct(
        public readonly Publication $publication,
        public readonly ?User $voter,
    ) {
    }
}
