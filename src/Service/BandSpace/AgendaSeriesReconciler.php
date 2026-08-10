<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\AgendaEntry;
use App\Repository\BandSpace\AgendaEntryExceptionRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps a recurring agenda entry and its cancellations coherent after a write.
 *
 * An AgendaEntryException only says "not on that date": it is matched against the expanded
 * occurrences by 'Y-m-d' and nothing ties it to the rule that produced the date in the first place.
 * So a write that changes the rule can leave cancellations pointing at dates the series no longer
 * has, and cancellations can also eat a short series whole.
 *
 * The cancellations are read through the repository rather than through `AgendaEntry::$exceptions`.
 * That collection is the inverse side of the association, so it only reflects what has been loaded
 * from the database: a row another caller has just persisted in the same request is not in it, and
 * neither is anything at all when the entry it hangs on was created in the same request.
 */
readonly class AgendaSeriesReconciler
{
    public function __construct(
        private AgendaAggregator $agendaAggregator,
        private AgendaEntryExceptionRepository $exceptionRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Drop the cancellations the recurrence rule no longer produces. The caller flushes.
     *
     * Move a Monday series to Tuesday and every cancelled Monday matches nothing: the occurrence
     * the band explicitly cancelled comes back either way, but the dead row would otherwise stay
     * forever and re-apply itself the day the rule happens to land on that date again. Truncating a
     * series has the same effect on the cancellations past the new horizon.
     */
    public function dropExceptionsOutsideRule(AgendaEntry $entry): void
    {
        $cancellations = $this->exceptionRepository->findByEntry($entry);
        if ($cancellations === []) {
            return;
        }

        // A rule with no horizon has no finite list of dates, so nothing can be shown to be outside
        // it. Only reachable if ValidRecurrence were bypassed, and dropping every cancellation on
        // that basis would destroy rows the band still wants.
        if ($entry->recurrenceFrequency !== null && $entry->recurrenceUntilDate === null) {
            return;
        }

        // Dropping the repetition leaves a single event, so the rule produces no date at all and
        // every cancellation goes: there is no occurrence left for one to point at.
        $ruleDates = array_flip($this->agendaAggregator->ruleOccurrenceDates($entry));

        foreach ($cancellations as $cancellation) {
            if (!isset($ruleDates[$cancellation->occurrenceDate->format('Y-m-d')])) {
                $this->entityManager->remove($cancellation);
            }
        }
    }

    /**
     * Whether the series still shows at least one occurrence nobody has cancelled.
     *
     * `$pendingCancellation` is a date the caller is about to cancel and has not written yet, so a
     * processor can ask the question before it commits.
     */
    public function hasLiveOccurrence(AgendaEntry $entry, ?DateTimeImmutable $pendingCancellation = null): bool
    {
        // A one-off entry is its own occurrence, and an open-ended rule keeps producing new ones
        // forever: neither can be cancelled down to nothing, so neither is ever cleaned up. Every
        // recurring entry does get a horizon from ValidRecurrence; the column is nullable only
        // because one-off entries share the table.
        if ($entry->recurrenceFrequency === null || $entry->recurrenceUntilDate === null) {
            return true;
        }

        $cancelledDates = $pendingCancellation instanceof DateTimeImmutable
            ? [$pendingCancellation->format('Y-m-d')]
            : [];
        foreach ($this->exceptionRepository->findByEntry($entry) as $cancellation) {
            $cancelledDates[] = $cancellation->occurrenceDate->format('Y-m-d');
        }

        return array_diff($this->agendaAggregator->ruleOccurrenceDates($entry), $cancelledDates) !== [];
    }
}
