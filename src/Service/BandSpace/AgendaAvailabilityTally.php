<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\AgendaEntryAvailability;
use App\Entity\BandSpace\MemberAbsence;

/**
 * Who can make one date (#1000), shared by the per-date list and the agenda feed so the two never
 * disagree. Only active members count. An explicit answer wins; without one, a declared absence
 * covering the date counts as `absent`; otherwise the member has not answered yet.
 */
final class AgendaAvailabilityTally
{
    public const string ABSENT = 'absent';

    /**
     * @param list<string>              $activeMembershipIds the members who count, in display order
     * @param AgendaEntryAvailability[] $answers             for this occurrence
     * @param MemberAbsence[]           $absences            of the band, any range
     *
     * @return array<string, string|null> membership id => 'yes' | 'no' | 'absent' | null
     */
    public static function resolve(array $activeMembershipIds, array $answers, array $absences, string $occurrenceDate): array
    {
        $answerByMember = [];
        foreach ($answers as $answer) {
            $answerByMember[(string) $answer->membership->id] = $answer->answer->value;
        }

        $absentMembers = [];
        foreach ($absences as $absence) {
            if ($absence->startDate->format('Y-m-d') <= $occurrenceDate && $absence->endDate->format('Y-m-d') >= $occurrenceDate) {
                $absentMembers[(string) $absence->member->id] = true;
            }
        }

        $resolved = [];
        foreach ($activeMembershipIds as $memberId) {
            $resolved[$memberId] = $answerByMember[$memberId] ?? (isset($absentMembers[$memberId]) ? self::ABSENT : null);
        }

        return $resolved;
    }

    /**
     * @param array<string, string|null> $resolved
     *
     * @return array{yes: int, no: int, absent: int, pending: int}
     */
    public static function totals(array $resolved): array
    {
        $counts = array_count_values(array_map(static fn (?string $answer): string => $answer ?? 'pending', $resolved));

        return [
            'yes' => $counts['yes'] ?? 0,
            'no' => $counts['no'] ?? 0,
            'absent' => $counts[self::ABSENT] ?? 0,
            'pending' => $counts['pending'] ?? 0,
        ];
    }
}
