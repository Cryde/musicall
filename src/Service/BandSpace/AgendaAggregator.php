<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\ApiResource\BandSpace\AgendaItem;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\MemberAbsence;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\AgendaRecurrenceFrequency;
use App\Enum\BandSpace\AgendaRecurrenceMonthlyMode;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\MemberAbsenceRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

readonly class AgendaAggregator
{
    /**
     * The civil timezone a band reads its agenda in. France and Belgium share it, which covers the
     * whole audience, and a per band space timezone would replace this single constant.
     */
    private const string CIVIL_TIMEZONE = 'Europe/Paris';

    public function __construct(
        private AgendaEntryRepository $agendaEntryRepository,
        private TaskRepository $taskRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private MemberAbsenceRepository $memberAbsenceRepository,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

    /**
     * @return AgendaItem[]
     */
    public function aggregate(
        BandSpace $bandSpace,
        BandSpaceMembership $viewer,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array
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
            ...array_map(fn(FinanceEntry $f): AgendaItem => $this->buildFinance($bandSpace, $f), $this->financeEntryRepository->findUpcomingForBand($bandSpace, $viewer, $from, $to)),
            ...array_map(fn(MemberAbsence $a): AgendaItem => $this->buildAbsence($bandSpace, $a), $this->memberAbsenceRepository->findOverlappingForBand($bandSpace, $from, $to)),
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
            // The stored anchor, which an expanded occurrence otherwise hides: `datetime` above is
            // the occurrence, and every occurrence carries the same `source_id`. The editor needs
            // the anchor to move a whole series by however much the user moved the occurrence they
            // opened, instead of writing that occurrence's date onto the anchor - which silently
            // drops every occurrence before it.
            'series_start_datetime' => $isRecurringOccurrence
                ? $entry->eventDatetime->format(DateTimeInterface::ATOM)
                : null,
        ];

        return $item;
    }

    /**
     * Every calendar date the entry's recurrence rule lands on, from its anchor to its horizon.
     *
     * Cancellations are deliberately not applied: this is the rule itself, which is what the write
     * side needs to tell a cancellation that still points at a real occurrence from one the rule no
     * longer produces. Empty for a one-off entry, and for a rule with no horizon, which has no
     * finite set of dates to list.
     *
     * @return string[] 'Y-m-d', in chronological order
     */
    public function ruleOccurrenceDates(AgendaEntry $entry): array
    {
        return array_map(
            static fn(DateTimeImmutable $occurrence): string => $occurrence->format('Y-m-d'),
            $this->expandRule($entry, $entry->eventDatetime, null),
        );
    }

    /**
     * Expand a recurring entry into the list of occurrence start datetimes whose date falls
     * within [$from, $to] and on or before `recurrenceUntilDate`.
     *
     * Cancelled occurrences (entries in `$entry->exceptions`) are filtered out by date here -
     * matching the user-perceived "this occurrence" granularity (date-only, not date+time).
     *
     * @return DateTimeImmutable[]
     */
    private function expandOccurrences(AgendaEntry $entry, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $occurrences = $this->expandRule($entry, $from, $to);

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
     * Every occurrence the recurrence rule lands on inside [$from, $to], cancellations included.
     * A `$to` of null means "as far as the horizon goes", which is what enumerating a whole series
     * needs. Bounded by the 5-year horizon enforced at validation time, so the iteration count is
     * safe.
     *
     * The stepping happens in civil time and the result is converted back to UTC. Adding a fixed
     * interval to a UTC instant preserves the UTC time of day, so a weekly 20:00 Paris rehearsal
     * anchored in winter starts showing as 21:00 the moment the clocks go forward, and keeps that
     * hour for the whole remainder of the series. Stepping in civil time preserves the wall clock,
     * which is what a band means by "every Monday at 20:00".
     *
     * @return DateTimeImmutable[]
     */
    private function expandRule(AgendaEntry $entry, DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        if ($entry->recurrenceFrequency === null || $entry->recurrenceUntilDate === null) {
            return [];
        }

        $timezone = $this->expansionTimezone($entry);
        $anchor = $entry->eventDatetime->setTimezone($timezone);

        // The horizon is a calendar date, so the series runs to the end of that civil day and an
        // occurrence scheduled later that day still counts.
        $horizon = new DateTimeImmutable($entry->recurrenceUntilDate->format('Y-m-d') . ' 23:59:59', $timezone);
        $windowEnd = $to instanceof DateTimeImmutable && $to < $horizon ? $to : $horizon;

        $occurrences = match ($entry->recurrenceFrequency) {
            AgendaRecurrenceFrequency::Daily => $this->expandFixedStep($anchor, $from, $windowEnd, 1),
            AgendaRecurrenceFrequency::Weekly => $this->expandFixedStep($anchor, $from, $windowEnd, 7),
            AgendaRecurrenceFrequency::Monthly => $entry->recurrenceMonthlyMode === AgendaRecurrenceMonthlyMode::ByWeekday
                ? $this->expandMonthlyByWeekday($anchor, $from, $windowEnd)
                : $this->expandMonthlyByDate($anchor, $from, $windowEnd),
            AgendaRecurrenceFrequency::Yearly => $this->expandYearly($anchor, $from, $windowEnd),
        };

        // Back to UTC. The occurrence is the same instant either way, and every consumer reads it
        // as UTC: the ATOM datetime on the item, the date an exception is matched on, and the date
        // the front end slices back off that ATOM string to cancel an occurrence.
        $utc = new DateTimeZone('UTC');

        return array_map(
            static fn(DateTimeImmutable $occurrence): DateTimeImmutable => $occurrence->setTimezone($utc),
            $occurrences,
        );
    }

    /**
     * The timezone a series is stepped in.
     *
     * A timed entry stores a real instant, so it is stepped in the civil timezone the band reads
     * its agenda in. An all-day entry stores no instant at all: it is a calendar date pinned at UTC
     * midnight by the create and update processors. Reading that marker in Paris and writing it
     * back would land it on 23:00 the day before every winter, which is the off-by-one #819 fixed
     * on the front end, so an all-day series keeps being stepped in UTC.
     */
    private function expansionTimezone(AgendaEntry $entry): DateTimeZone
    {
        return new DateTimeZone($entry->isAllDay ? 'UTC' : self::CIVIL_TIMEZONE);
    }

    /**
     * Every occurrence is measured from the anchor rather than from the one before it.
     *
     * Chaining the cursor makes any normalisation permanent. An occurrence that lands in the hour
     * the clocks skip on the spring forward night (02:00 to 03:00 does not exist) is pushed to
     * 03:30 by PHP, and stepping on from there keeps every later occurrence at 03:30 for the rest
     * of the series. Measuring from the anchor confines the shift to the one occurrence that really
     * has no valid time, which is what the monthly and yearly expanders already do by recomputing
     * from the anchor with setDate().
     *
     * @return DateTimeImmutable[]
     */
    private function expandFixedStep(DateTimeImmutable $start, DateTimeImmutable $from, DateTimeImmutable $windowEnd, int $stepDays): array
    {
        $occurrences = [];
        for ($stepCount = 0; ; ++$stepCount) {
            $occurrence = $start->add(new DateInterval('P' . ($stepCount * $stepDays) . 'D'));
            if ($occurrence > $windowEnd) {
                break;
            }
            if ($occurrence >= $from) {
                $occurrences[] = $occurrence;
            }
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

    /**
     * A member's unavailability, as an all day band rather than an appointment: it is context for
     * the dates around it, not something anybody attends.
     *
     * The range is a pair of calendar dates and carries no instant, so both ends are pinned to
     * midnight UTC the way an all day entry is - see AgendaEntryCreateProcessor for why that pin
     * matters, and assets/js/utils/agendaDate.js for how the front end unpins it.
     */
    private function buildAbsence(BandSpace $bandSpace, MemberAbsence $absence): AgendaItem
    {
        $displayName = $absence->member->displayName();

        $item = new AgendaItem();
        $item->id = 'absence-' . $absence->id;
        $item->bandSpaceId = (string) $bandSpace->id;
        $item->source = 'absence';
        $item->sourceId = (string) $absence->id;
        $item->datetime = $this->pinToUtcMidnight($absence->startDate);
        $item->endDatetime = $this->pinToUtcMidnight($absence->endDate);
        $item->isAllDay = true;
        $item->title = $displayName . ' indisponible';
        $item->description = $absence->reason;
        // Only what a consumer reads. The member's name is already in the title, and the reason is
        // already the description, which is where both the list row and the calendar chip read it.
        $item->metadata = ['member_id' => (string) $absence->member->id];

        return $item;
    }

    /**
     * A date-only column as an ATOM instant at midnight UTC, which is how every all day item on the
     * agenda is carried - see AgendaEntryCreateProcessor for why the pin matters, and
     * assets/js/utils/agendaDate.js for how the front end takes it back off.
     *
     * Built by concatenation rather than by parsing: ATOM on a +00:00 date is
     * `Y-m-d\TH:i:s+00:00`, so constructing a DateTimeImmutable only to format it again would
     * return the string it was handed.
     */
    private function pinToUtcMidnight(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d') . 'T00:00:00+00:00';
    }

    private function buildFinance(BandSpace $bandSpace, FinanceEntry $entry): AgendaItem
    {
        $item = new AgendaItem();
        $item->id = 'finance-' . $entry->id;
        $item->bandSpaceId = (string) $bandSpace->id;
        $item->source = 'finance';
        $item->sourceId = (string) $entry->id;
        $item->datetime = $this->pinToUtcMidnight($entry->date);
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
