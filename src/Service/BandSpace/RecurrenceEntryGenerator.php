<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\RecurrenceInterval;

readonly class RecurrenceEntryGenerator
{
    /**
     * Every occurrence of the recurrence, from its start date up to its end date.
     *
     * @return FinanceEntry[]
     */
    public function generateEntries(FinanceRecurrence $recurrence, ?BandSpaceMembership $member = null): array
    {
        return array_map(
            fn (\DateTimeInterface $date): FinanceEntry => $this->buildEntry($recurrence, $member, $date),
            $this->occurrenceDates($recurrence),
        );
    }

    /**
     * The occurrences falling strictly after $after that the recurrence has not materialised yet.
     *
     * Used when a recurrence is extended and when it is switched back on. Skipping the dates that already
     * carry an entry is what makes both operations repeatable: toggling a recurrence off and on, or
     * pushing its end date out twice, can never plant a second entry on a date that already has one.
     *
     * @param string[] $takenDates Y-m-d dates the recurrence already has an entry on, whatever its status
     * @return FinanceEntry[]
     */
    public function generateMissingEntriesAfter(
        FinanceRecurrence $recurrence,
        ?BandSpaceMembership $member,
        \DateTimeInterface $after,
        array $takenDates,
    ): array {
        $entries = [];

        foreach ($this->occurrenceDates($recurrence) as $date) {
            if ($date <= $after || in_array($date->format('Y-m-d'), $takenDates, true)) {
                continue;
            }

            $entries[] = $this->buildEntry($recurrence, $member, $date);
        }

        return $entries;
    }

    /**
     * The grid a recurrence lands on: anchored on its start date, stepping by its interval, never past its
     * end date. Every entry a recurrence ever materialises sits on this grid, which is why the start date
     * and the interval are frozen once the first entries exist.
     *
     * @return \DateTimeInterface[]
     */
    private function occurrenceDates(FinanceRecurrence $recurrence): array
    {
        $dates = [];
        $current = $recurrence->startDate;

        while ($current <= $recurrence->endDate) {
            $dates[] = clone $current;
            $current = $this->nextDate($current, $recurrence->interval);
        }

        return $dates;
    }

    private function buildEntry(FinanceRecurrence $recurrence, ?BandSpaceMembership $member, \DateTimeInterface $date): FinanceEntry
    {
        $entry = new FinanceEntry();
        $entry->category = $recurrence->category;
        $entry->label = $recurrence->label;
        $entry->type = $recurrence->type;
        $entry->amount = $recurrence->amount;
        $entry->scope = $recurrence->scope;
        $entry->status = FinanceEntryStatus::Planned;
        $entry->date = clone $date;
        $entry->recurrence = $recurrence;
        $entry->member = $member;

        return $entry;
    }

    private function nextDate(\DateTimeInterface $date, RecurrenceInterval $interval): \DateTimeInterface
    {
        $next = \DateTime::createFromInterface($date);

        return match ($interval) {
            RecurrenceInterval::Weekly => $next->modify('+7 days'),
            RecurrenceInterval::Monthly => $this->addMonths($next, 1),
            RecurrenceInterval::Quarterly => $this->addMonths($next, 3),
            RecurrenceInterval::Yearly => $this->addMonths($next, 12),
        };
    }

    private function addMonths(\DateTime $date, int $months): \DateTime
    {
        $day = (int) $date->format('j');
        $date->modify("+{$months} months");

        // Cap to end of month if the day overflowed (e.g. Jan 31 + 1 month = Feb 28)
        if ((int) $date->format('j') !== $day) {
            $date->modify('last day of previous month');
        }

        return $date;
    }
}
