<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use Symfony\Component\Validator\Constraints as Assert;

class AgendaEntryAvailabilityReminder
{
    /** Optional for a one-off entry, required for an occurrence of a recurring one. */
    #[Assert\Date(message: 'band_space.agenda.date_invalid')]
    public ?string $occurrenceDate = null;
}
