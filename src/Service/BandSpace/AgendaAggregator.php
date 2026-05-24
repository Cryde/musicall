<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\ApiResource\BandSpace\AgendaItem;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\AgendaRecurrenceFrequency;
use App\Enum\BandSpace\AgendaRecurrenceMonthlyMode;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

readonly class AgendaAggregator
{
    public function __construct(
        private AgendaEntryRepository $agendaEntryRepository,
        private TaskRepository $taskRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

    /**
     * @return AgendaItem[]
     */
    public function aggregate(BandSpace $bandSpace, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $manualItems = [];
        foreach ($this->agendaEntryRepository->findUpcomingForBand($bandSpace, $from, $to) as $entry) {
            if ($entry->recurrenceFrequency === null) {
                $manualItems[] = $this->buildManual($bandSpace, $entry, $entry->eventDatetime, $entry->endDatetime);
                continue;
            }

            foreach ($this->expandOccurrences($entry, $from, $to) as $occurrenceStart) {
                $occurrenceEnd = $this->shiftEnd($entry->eventDatetime, $entry->endDatetime, $occurrenceStart);
                $manualItems[] = $this->buildManual($bandSpace, $entry, $occurrenceStart, $occurrenceEnd);
            }
        }

        $items = [
            ...$manualItems,
            ...array_map(fn(Task $t): AgendaItem => $this->buildTask($bandSpace, $t), $this->taskRepository->findUpcomingForBand($bandSpace, $from, $to)),
            ...array_map(fn(FinanceEntry $f): AgendaItem => $this->buildFinance($bandSpace, $f), $this->financeEntryRepository->findUpcomingForBand($bandSpace, $from, $to)),
        ];

        usort(
            $items,
            fn(AgendaItem $a, AgendaItem $b): int => strcmp($a->datetime, $b->datetime) ?: strcmp($a->source, $b->source) ?: strcmp($a->sourceId, $b->sourceId),
        );

        return $items;
    }

    private function buildManual(
        BandSpace $bandSpace,
        AgendaEntry $entry,
        DateTimeImmutable $occurrenceStart,
        ?DateTimeImmutable $occurrenceEnd,
    ): AgendaItem {
        $isRecurringOccurrence = $entry->recurrenceFrequency !== null;
        $occurrenceKey = $occurrenceStart->format('Ymd-Hi');

        $item = new AgendaItem();
        $item->id = $isRecurringOccurrence
            ? 'manual-' . $entry->id . '-' . $occurrenceKey
            : 'manual-' . $entry->id;
        $item->bandSpaceId = (string) $bandSpace->id;
        $item->source = 'manual';
        $item->sourceId = (string) $entry->id;
        $item->datetime = $occurrenceStart->format(DateTimeInterface::ATOM);
        $item->endDatetime = $occurrenceEnd?->format(DateTimeInterface::ATOM);
        $item->isAllDay = $entry->isAllDay;
        $item->title = $entry->title;
        $item->description = $entry->description;
        $item->metadata = [
            'location' => $entry->location,
            'is_recurring_occurrence' => $isRecurringOccurrence,
            'recurrence_frequency' => $entry->recurrenceFrequency?->value,
            'recurrence_monthly_mode' => $entry->recurrenceMonthlyMode?->value,
            'recurrence_until_date' => $entry->recurrenceUntilDate?->format('Y-m-d'),
            'series_id' => $isRecurringOccurrence ? (string) $entry->id : null,
        ];

        return $item;
    }

    /**
     * Expand a recurring entry into the list of occurrence start datetimes whose date falls
     * within [$from, $to] and on or before `recurrenceUntilDate`. Bounded by the 5-year horizon
     * enforced at validation time, so the iteration count is safe.
     *
     * Cancelled occurrences (entries in `$entry->exceptions`) are filtered out by date here -
     * matching the user-perceived "this occurrence" granularity (date-only, not date+time).
     *
     * @return DateTimeImmutable[]
     */
    private function expandOccurrences(AgendaEntry $entry, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        if ($entry->recurrenceFrequency === null || $entry->recurrenceUntilDate === null) {
            return [];
        }

        // Use the end of `recurrenceUntilDate` so occurrences scheduled later that day still count.
        $horizon = $entry->recurrenceUntilDate->setTime(23, 59, 59);
        $windowEnd = $to < $horizon ? $to : $horizon;

        $occurrences = match ($entry->recurrenceFrequency) {
            AgendaRecurrenceFrequency::Daily => $this->expandFixedStep($entry->eventDatetime, $from, $windowEnd, new DateInterval('P1D')),
            AgendaRecurrenceFrequency::Weekly => $this->expandFixedStep($entry->eventDatetime, $from, $windowEnd, new DateInterval('P7D')),
            AgendaRecurrenceFrequency::Monthly => $entry->recurrenceMonthlyMode === AgendaRecurrenceMonthlyMode::ByWeekday
                ? $this->expandMonthlyByWeekday($entry->eventDatetime, $from, $windowEnd)
                : $this->expandMonthlyByDate($entry->eventDatetime, $from, $windowEnd),
            AgendaRecurrenceFrequency::Yearly => $this->expandYearly($entry->eventDatetime, $from, $windowEnd),
        };

        if ($entry->exceptions->isEmpty()) {
            return $occurrences;
        }

        $excludedDates = [];
        foreach ($entry->exceptions as $exception) {
            $excludedDates[$exception->occurrenceDate->format('Y-m-d')] = true;
        }

        return array_values(array_filter(
            $occurrences,
            static fn(DateTimeImmutable $occurrence): bool => !isset($excludedDates[$occurrence->format('Y-m-d')]),
        ));
    }

    /**
     * @return DateTimeImmutable[]
     */
    private function expandFixedStep(DateTimeImmutable $start, DateTimeImmutable $from, DateTimeImmutable $windowEnd, DateInterval $step): array
    {
        $occurrences = [];
        $cursor = $start;
        while ($cursor <= $windowEnd) {
            if ($cursor >= $from) {
                $occurrences[] = $cursor;
            }
            $cursor = $cursor->add($step);
        }

        return $occurrences;
    }

    /**
     * @return DateTimeImmutable[]
     */
    private function expandMonthlyByDate(DateTimeImmutable $start, DateTimeImmutable $from, DateTimeImmutable $windowEnd): array
    {
        $anchorDay = (int) $start->format('j');
        $occurrences = [];

        $year = (int) $start->format('Y');
        $month = (int) $start->format('n');

        while (true) {
            $daysInMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
            $day = min($anchorDay, $daysInMonth);
            $candidate = $start->setDate($year, $month, $day);

            if ($candidate > $windowEnd) {
                break;
            }
            if ($candidate >= $from) {
                $occurrences[] = $candidate;
            }

            ++$month;
            if ($month === 13) {
                $month = 1;
                ++$year;
            }
        }

        return $occurrences;
    }

    /**
     * Same nth-weekday-of-month pattern as the anchor (e.g. "second Tuesday"). Skips months
     * where the slot doesn't exist (e.g. "fifth Monday" in a month with only four Mondays).
     *
     * @return DateTimeImmutable[]
     */
    private function expandMonthlyByWeekday(DateTimeImmutable $start, DateTimeImmutable $from, DateTimeImmutable $windowEnd): array
    {
        $anchorDay = (int) $start->format('j');
        $weekIndex = (int) floor(($anchorDay - 1) / 7) + 1; // 1..5
        $weekday = (int) $start->format('w'); // 0 (Sun) .. 6 (Sat)

        $occurrences = [];
        $year = (int) $start->format('Y');
        $month = (int) $start->format('n');

        while (true) {
            $candidate = $this->nthWeekdayOfMonth($year, $month, $weekIndex, $weekday, $start);

            // Stop only when we've gone past the window with a valid candidate; null candidates
            // (slot doesn't exist this month) are skipped without breaking the loop.
            if ($candidate instanceof DateTimeImmutable) {
                if ($candidate > $windowEnd) {
                    break;
                }
                if ($candidate >= $from) {
                    $occurrences[] = $candidate;
                }
            }

            ++$month;
            if ($month === 13) {
                $month = 1;
                ++$year;
            }

            // Safety: cap iteration if recurrenceUntilDate were missing somehow (shouldn't happen — validated).
            if ($year > (int) $start->format('Y') + 100) {
                break;
            }
        }

        return $occurrences;
    }

    private function nthWeekdayOfMonth(int $year, int $month, int $weekIndex, int $weekday, DateTimeImmutable $template): ?DateTimeImmutable
    {
        $firstOfMonth = $template->setDate($year, $month, 1);
        $firstWeekday = (int) $firstOfMonth->format('w');
        $offset = ($weekday - $firstWeekday + 7) % 7;
        $day = 1 + $offset + ($weekIndex - 1) * 7;

        $daysInMonth = (int) $firstOfMonth->format('t');
        if ($day > $daysInMonth) {
            return null;
        }

        return $template->setDate($year, $month, $day);
    }

    /**
     * @return DateTimeImmutable[]
     */
    private function expandYearly(DateTimeImmutable $start, DateTimeImmutable $from, DateTimeImmutable $windowEnd): array
    {
        $anchorMonth = (int) $start->format('n');
        $anchorDay = (int) $start->format('j');
        $occurrences = [];

        $year = (int) $start->format('Y');
        while (true) {
            $daysInMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $anchorMonth)))->format('t');
            $day = min($anchorDay, $daysInMonth); // Feb 29 clamps to Feb 28 on non-leap years.
            $candidate = $start->setDate($year, $anchorMonth, $day);

            if ($candidate > $windowEnd) {
                break;
            }
            if ($candidate >= $from) {
                $occurrences[] = $candidate;
            }

            ++$year;
        }

        return $occurrences;
    }

    private function shiftEnd(DateTimeImmutable $originalStart, ?DateTimeImmutable $originalEnd, DateTimeImmutable $occurrenceStart): ?DateTimeImmutable
    {
        if (!$originalEnd instanceof DateTimeImmutable) {
            return null;
        }

        $durationSeconds = $originalEnd->getTimestamp() - $originalStart->getTimestamp();

        return $occurrenceStart->modify('+' . $durationSeconds . ' seconds');
    }

    private function buildTask(BandSpace $bandSpace, Task $task): AgendaItem
    {
        $item = new AgendaItem();
        $item->id = 'task-' . $task->id;
        $item->bandSpaceId = (string) $bandSpace->id;
        $item->source = 'task';
        $item->sourceId = (string) $task->id;
        $item->datetime = $task->dueDate?->format(DateTimeInterface::ATOM) ?? '';
        $item->endDatetime = null;
        $item->isAllDay = false;
        $item->title = $task->title;
        $item->description = $task->description;
        $item->metadata = [
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'category_name' => $task->category?->name,
            'assignees' => array_values($task->assignees->map(
                fn(User $user): array => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'profile_picture_url' => $this->profilePictureUrlBuilder->build($user),
                ]
            )->toArray()),
        ];

        return $item;
    }

    private function buildFinance(BandSpace $bandSpace, FinanceEntry $entry): AgendaItem
    {
        $item = new AgendaItem();
        $item->id = 'finance-' . $entry->id;
        $item->bandSpaceId = (string) $bandSpace->id;
        $item->source = 'finance';
        $item->sourceId = (string) $entry->id;
        $item->datetime = DateTimeImmutable::createFromInterface($entry->date)->setTime(0, 0)->format(DateTimeInterface::ATOM);
        $item->endDatetime = null;
        $item->isAllDay = false;
        $item->title = $entry->label;
        $item->description = null;
        $item->metadata = [
            'type' => $entry->type->value,
            'status' => $entry->status->value,
            'scope' => $entry->scope->value,
            'amount' => $entry->amount,
            'amount_min' => $entry->amountMin,
            'amount_max' => $entry->amountMax,
            'category_name' => $entry->category->name,
        ];

        return $item;
    }
}
