<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace;

use App\Entity\BandSpace\AgendaEntryAvailability;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\MemberAbsence;
use App\Enum\BandSpace\AvailabilityAnswer;
use App\Service\BandSpace\AgendaAvailabilityTally;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AgendaAvailabilityTallyTest extends TestCase
{
    public function test_an_answer_wins_over_an_absence_and_an_absence_over_no_answer(): void
    {
        $answers = [
            $this->answer('answered-yes', AvailabilityAnswer::Yes),
            // Answered despite a declared absence: their word is what counts.
            $this->answer('absent-but-yes', AvailabilityAnswer::Yes),
            $this->answer('answered-no', AvailabilityAnswer::No),
            // Left the band since: not one of the ids, so not counted.
            $this->answer('former', AvailabilityAnswer::No),
        ];
        $absences = [
            $this->absence('absent-but-yes', '2026-10-01', '2026-10-31'),
            $this->absence('absent', '2026-10-10', '2026-10-10'),
            $this->absence('away-another-day', '2026-10-11', '2026-10-20'),
        ];

        $resolved = AgendaAvailabilityTally::resolve(
            ['answered-yes', 'absent-but-yes', 'answered-no', 'absent', 'away-another-day', 'silent'],
            $answers,
            $absences,
            '2026-10-10',
        );

        $this->assertSame([
            'answered-yes' => 'yes',
            'absent-but-yes' => 'yes',
            'answered-no' => 'no',
            'absent' => 'absent',
            'away-another-day' => null,
            'silent' => null,
        ], $resolved);
        $this->assertSame(['yes' => 2, 'no' => 1, 'absent' => 1, 'pending' => 2], AgendaAvailabilityTally::totals($resolved));
    }

    public function test_nobody_in_the_band_counts_nothing(): void
    {
        $this->assertSame(['yes' => 0, 'no' => 0, 'absent' => 0, 'pending' => 0], AgendaAvailabilityTally::totals([]));
    }

    private function membership(string $id): BandSpaceMembership
    {
        $membership = new BandSpaceMembership();
        $membership->id = $id;

        return $membership;
    }

    private function answer(string $membershipId, AvailabilityAnswer $answer): AgendaEntryAvailability
    {
        $availability = new AgendaEntryAvailability();
        $availability->membership = $this->membership($membershipId);
        $availability->answer = $answer;

        return $availability;
    }

    private function absence(string $membershipId, string $from, string $to): MemberAbsence
    {
        $absence = new MemberAbsence();
        $absence->member = $this->membership($membershipId);
        $absence->startDate = new DateTimeImmutable($from);
        $absence->endDate = new DateTimeImmutable($to);

        return $absence;
    }
}
