<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceEntryResource;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceEntrySplitRepository;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * The mirror of SplitNotPersonal, which refuses a split on an entry that is already personal. Without
 * this one the same state was reachable in reverse: split a band entry between members, then turn the
 * entry personal. The splits stayed behind, naming members who could no longer see the entry they
 * belonged to and still weighing on their contribution total.
 *
 * Two write paths reach that flip, so both are guarded here. A recurrence PATCH pushes the series scope
 * onto every forecast it still owns, which turns those entries personal without ever touching the entry
 * endpoint.
 */
class PersonalScopeWithoutSplitsValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_02e21b18-a797-46ed-83a2-aaacf5ee1992';

    public function __construct(
        private readonly FinanceEntryRepository $entryRepository,
        private readonly FinanceRecurrenceRepository $recurrenceRepository,
        private readonly FinanceEntrySplitRepository $splitRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PersonalScopeWithoutSplits) {
            throw new UnexpectedTypeException($constraint, PersonalScopeWithoutSplits::class);
        }

        if (!$value instanceof FinanceEntryResource && !$value instanceof FinanceRecurrenceResource) {
            return;
        }

        if (!isset($value->id, $value->scope) || $value->scope !== FinanceEntryScope::Personal->value) {
            return;
        }

        if ($value instanceof FinanceEntryResource) {
            $this->validateEntry($value->id, $constraint);

            return;
        }

        $this->validateRecurrence($value->id, $constraint);
    }

    private function validateEntry(string $entryId, PersonalScopeWithoutSplits $constraint): void
    {
        $entry = $this->entryRepository->find($entryId);

        // Only the transition is refused. An entry that is already personal stays editable even if rows
        // written before this rule left splits on it, otherwise a PATCH of its libellé would be refused
        // by a rule it can do nothing about.
        if (!$entry instanceof FinanceEntry || $entry->scope !== FinanceEntryScope::Band) {
            return;
        }

        if ($this->splitRepository->countByEntries([$entry]) > 0) {
            $this->addViolation($constraint->message);
        }
    }

    private function validateRecurrence(string $recurrenceId, PersonalScopeWithoutSplits $constraint): void
    {
        $recurrence = $this->recurrenceRepository->find($recurrenceId);

        if (!$recurrence instanceof FinanceRecurrence || $recurrence->scope !== FinanceEntryScope::Band) {
            return;
        }

        // Exactly the forecasts FinanceRecurrenceUpdateProcessor::syncFutureForecasts() would rewrite:
        // a split sitting on an occurrence the edit leaves alone is no reason to refuse the edit.
        $forecasts = $this->entryRepository->findPlannedByRecurrenceAfter($recurrence, new \DateTime());

        if ($this->splitRepository->countByEntries($forecasts) > 0) {
            $this->addViolation($constraint->recurrenceMessage);
        }
    }

    private function addViolation(string $message): void
    {
        $this->context->buildViolation($message)
            ->atPath('scope')
            ->setCode(self::ERROR_CODE)
            ->addViolation();
    }
}
