<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use Symfony\Component\Validator\Constraints as Assert;

class AgendaEntryAvailabilityReminder
{
    /** Optional for a one-off entry, required for an occurrence of a recurring one. */
    #[Assert\Date(message: 'Le format de la date est invalide (attendu : AAAA-MM-JJ)')]
    public ?string $occurrenceDate = null;
}
