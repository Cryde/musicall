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
     * @return FinanceEntry[]
     */
    public function generateEntries(FinanceRecurrence $recurrence, ?BandSpaceMembership $member = null, ?\DateTimeInterface $fromDate = null): array
    {
        $entries = [];
        $current = $fromDate ?? $recurrence->startDate;

        while ($current <= $recurrence->endDate) {
            $entry = new FinanceEntry();
            $entry->category = $recurrence->category;
            $entry->label = $recurrence->label;
            $entry->type = $recurrence->type;
            $entry->amount = $recurrence->amount;
            $entry->scope = $recurrence->scope;
            $entry->status = FinanceEntryStatus::Planned;
            $entry->date = clone $current;
            $entry->recurrence = $recurrence;
            $entry->member = $member;

            $entries[] = $entry;
            $current = $this->nextDate($current, $recurrence->interval);
        }

        return $entries;
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
