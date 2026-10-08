<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\AgendaEntryAvailability;
use App\Entity\BandSpace\MemberAbsence;

/**
 * What the agenda feed loaded once for a whole window, so each occurrence's availability totals
 * cost no query of their own.
 */
final readonly class AgendaAvailabilityContext
{
    /** @var array<string, AgendaEntryAvailability[]> "entry id|Y-m-d" => answers */
    private array $answersByOccurrence;

    /**
     * @param list<string>              $activeMembershipIds
     * @param AgendaEntryAvailability[] $answers
     * @param MemberAbsence[]           $absences
     */
    public function __construct(
        private array $activeMembershipIds,
        array $answers,
        private array $absences,
    ) {
        $byOccurrence = [];
        foreach ($answers as $answer) {
            $byOccurrence[self::key((string) $answer->agendaEntry->id, $answer->occurrenceDate->format('Y-m-d'))][] = $answer;
        }
        $this->answersByOccurrence = $byOccurrence;
    }

    /**
     * @return array{yes: int, no: int, absent: int, pending: int}
     */
    public function totalsFor(AgendaEntry $entry, string $occurrenceDate): array
    {
        return AgendaAvailabilityTally::totals(AgendaAvailabilityTally::resolve(
            $this->activeMembershipIds,
            $this->answersByOccurrence[self::key((string) $entry->id, $occurrenceDate)] ?? [],
            $this->absences,
            $occurrenceDate,
        ));
    }

    private static function key(string $entryId, string $occurrenceDate): string
    {
        return $entryId . '|' . $occurrenceDate;
    }
}
