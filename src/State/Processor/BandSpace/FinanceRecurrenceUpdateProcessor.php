<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFinanceActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\RecurrenceInterval;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\FinanceRecurrenceOwnerChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileSourceDetacher;
use App\Service\BandSpace\RecurrenceEntryGenerator;
use App\Service\Builder\BandSpace\FinanceRecurrenceBuilder;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Editing a recurrence has to reach the entries it already materialised, otherwise the sidebar card and
 * the budget disagree. Which entries may be touched follows one rule: an entry belongs to its recurrence
 * only while it is still a forecast, that is Planned and dated in the future.
 *
 * - Paid is accounting history and Committed means somebody has already engaged that occurrence at the
 *   amount it carries, so both are frozen whatever the edit.
 * - A Planned entry dated in the past is a bill that was already due; it is not repriced retroactively.
 * - The start date and the interval define the grid every materialised entry sits on, frozen entries
 *   included, so changing either is refused rather than half applied.
 *
 * Who may run the edit at all is FinanceRecurrenceOwnerChecker's business: a personal recurrence answers
 * only to the member its forecasts belong to.
 *
 * @implements ProcessorInterface<FinanceRecurrenceResource, FinanceRecurrenceResource>
 */
readonly class FinanceRecurrenceUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceRecurrenceOwnerChecker $recurrenceOwnerChecker,
        private FinanceRecurrenceRepository $financeRecurrenceRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private FinanceRecurrenceBuilder $financeRecurrenceBuilder,
        private RecurrenceEntryGenerator $recurrenceEntryGenerator,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private BandSpaceFileSourceDetacher $fileSourceDetacher,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param FinanceRecurrenceResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FinanceRecurrenceResource
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace, $currentMembership] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $recurrence = $this->financeRecurrenceRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$recurrence instanceof FinanceRecurrence) {
            throw new NotFoundHttpException('Récurrence introuvable');
        }

        // The owner of a personal recurrence, and the caller's own membership for a band one. Everything
        // this processor materialises is filed under it rather than under whoever is calling, so an edit
        // can never split one series between two members.
        $ownerMembership = $this->recurrenceOwnerChecker->checkCanUpdate($recurrence, $currentMembership);

        $requestPayload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $this->assertScheduleUnchanged($recurrence, $data, $requestPayload);

        $now = new DateTime();
        $oldLabel = $recurrence->label;
        $oldType = $recurrence->type;
        $oldAmount = $recurrence->amount;
        $oldScope = $recurrence->scope;
        $oldIsActive = $recurrence->isActive;

        if (array_key_exists('label', $requestPayload)) {
            $recurrence->label = $data->label;
        }

        if (array_key_exists('type', $requestPayload)) {
            $recurrence->type = FinanceEntryType::from($data->type);
        }

        if (array_key_exists('amount', $requestPayload)) {
            $recurrence->amount = $data->amount;
        }

        if (array_key_exists('scope', $requestPayload)) {
            $recurrence->scope = FinanceEntryScope::from($data->scope);
        }

        if (array_key_exists('is_active', $requestPayload)) {
            $recurrence->isActive = $data->isActive;
        }

        // From here to the flush runs in one transaction: the removals go out through bulk DQL
        // deletes that reach the database immediately, while the files detached alongside them wait
        // for the flush. Unwrapped, a flush that failed would leave the entries gone and their
        // attachments orphaned, which is the leak the detacher exists to close.
        [$updatedEntryCount, $removedEntryCount, $createdEntryCount] = $this->entityManager->wrapInTransaction(
            function () use (
                $recurrence,
                $data,
                $requestPayload,
                $bandSpace,
                $user,
                $ownerMembership,
                $now,
                $oldLabel,
                $oldType,
                $oldAmount,
                $oldScope,
                $oldIsActive,
            ): array {
                $createdEntries = [];
                $removedEntryCount = 0;

                $endDateChanged = false;
                $oldEndDateString = $recurrence->endDate->format('Y-m-d');
                $newEndDateString = $oldEndDateString;
                $extendedFrom = null;
                if (array_key_exists('end_date', $requestPayload)) {
                    $oldEndDate = $recurrence->endDate;
                    $newEndDate = new DateTime($data->endDate);

                    $recurrence->endDate = $newEndDate;
                    $newEndDateString = $newEndDate->format('Y-m-d');
                    $endDateChanged = $oldEndDateString !== $newEndDateString;

                    if ($newEndDate > $oldEndDate) {
                        $extendedFrom = $oldEndDate;
                    } elseif ($newEndDate < $oldEndDate) {
                        // The end date says when the series stops existing at all, so every forecast
                        // past it goes, including the ones already in the past.
                        $removedEntryCount += $this->dropPlannedAfter($recurrence, $bandSpace, $user, $newEndDate);
                    }
                }

                $reactivated = !$oldIsActive && $recurrence->isActive;
                if ($oldIsActive && !$recurrence->isActive) {
                    $removedEntryCount += $this->dropPlannedAfter($recurrence, $bandSpace, $user, $now);
                }

                // One materialisation, from the earlier of the two reasons to run it, and only while
                // the recurrence is actually running. Two calls would each ask the database which
                // dates are taken without seeing the other's pending inserts, so extending and
                // reactivating in the same PATCH would double every occurrence they both cover. And a
                // stopped recurrence must not refill the budget just because its end date moved.
                $materialiseFrom = $this->earlierOf($extendedFrom, $reactivated ? $now : null);
                if ($recurrence->isActive && $materialiseFrom instanceof DateTimeInterface) {
                    $createdEntries = $this->materialiseAfter($recurrence, $ownerMembership, $materialiseFrom);
                }

                foreach ($createdEntries as $entry) {
                    $this->entityManager->persist($entry);
                }

                // Runs after the removals so a deactivated recurrence has nothing left to rewrite, and
                // before the flush so the entries just built are not counted twice: they already carry
                // the new values. Gated on a real change, because the sync flattens any estimate range
                // it meets: an edit that moved only the end date would otherwise wipe a bracket
                // somebody set on a forecast by hand.
                $propagatingFieldChanged = $oldLabel !== $recurrence->label
                    || $oldType !== $recurrence->type
                    || $oldAmount !== $recurrence->amount
                    || $oldScope !== $recurrence->scope;
                $updatedEntryCount = $propagatingFieldChanged
                    ? $this->syncFutureForecasts($recurrence, $ownerMembership, $now)
                    : 0;

                $recurrence->updateDatetime = new DateTime();

                $this->recordChanges(
                    $recurrence,
                    $user,
                    $oldLabel,
                    $oldType,
                    $oldAmount,
                    $oldScope,
                    $oldIsActive,
                    $endDateChanged,
                    $oldEndDateString,
                    $newEndDateString,
                );

                return [$updatedEntryCount, $removedEntryCount, count($createdEntries)];
            }
        );

        // The entries were created and dropped underneath the recurrence's own collection, the removals
        // through a bulk delete that the ORM never sees, so the count it reports is only trustworthy once
        // the collection has been read back from the database.
        $this->entityManager->refresh($recurrence);

        return $this->financeRecurrenceBuilder->buildItem(
            $recurrence,
            updatedEntryCount: $updatedEntryCount,
            removedEntryCount: $removedEntryCount,
            createdEntryCount: $createdEntryCount,
        );
    }

    /**
     * The start date and the interval are the two things the occurrence grid is built on, and every entry
     * already materialised sits on that grid, paid ones included. Re-anchoring it would either move
     * accounting history or leave one recurrence straddling two incompatible schedules, so the change is
     * refused instead of being silently dropped: end this recurrence and create the new one.
     *
     * @param array<string, mixed> $requestPayload
     */
    private function assertScheduleUnchanged(FinanceRecurrence $recurrence, FinanceRecurrenceResource $data, array $requestPayload): void
    {
        if (
            array_key_exists('start_date', $requestPayload)
            && (new DateTime($data->startDate))->format('Y-m-d') !== $recurrence->startDate->format('Y-m-d')
        ) {
            throw new UnprocessableEntityHttpException(
                'La date de début d\'une récurrence ne peut plus être modifiée. Terminez celle-ci et créez une nouvelle récurrence.'
            );
        }

        if (
            array_key_exists('interval', $requestPayload)
            && RecurrenceInterval::tryFrom($data->interval) !== $recurrence->interval
        ) {
            throw new UnprocessableEntityHttpException(
                'L\'intervalle d\'une récurrence ne peut plus être modifié. Terminez celle-ci et créez une nouvelle récurrence.'
            );
        }
    }

    /**
     * Drops the forecasts past a date, clearing the file attachments hanging on them first.
     *
     * The delete is bulk DQL, so nothing cascades to `band_space_file_attachment`, whose `source_id`
     * carries no foreign key. An attachment left naming an entry that no longer exists locks its file
     * for good: the delete endpoint refuses an attached file and the detach endpoint has no source to
     * answer for. Both removal paths in this processor go through here so neither can forget.
     *
     * @return int how many entries were dropped
     */
    private function dropPlannedAfter(
        FinanceRecurrence $recurrence,
        BandSpace $bandSpace,
        User $user,
        DateTimeInterface $after,
    ): int {
        $this->fileSourceDetacher->detachDeletedSources(
            $bandSpace,
            'finance',
            $this->financeEntryRepository->findPlannedLabelsByRecurrence($recurrence, $after),
            $user,
        );

        return $this->financeEntryRepository->deletePlannedByRecurrence($recurrence, $after);
    }

    /** The earlier of two optional moments, or null when neither is set. */
    private function earlierOf(?DateTimeInterface $first, ?DateTimeInterface $second): ?DateTimeInterface
    {
        if (!$first instanceof DateTimeInterface) {
            return $second;
        }

        if (!$second instanceof DateTimeInterface) {
            return $first;
        }

        return $first < $second ? $first : $second;
    }

    /**
     * @return FinanceEntry[]
     */
    private function materialiseAfter(FinanceRecurrence $recurrence, BandSpaceMembership $ownerMembership, DateTimeInterface $after): array
    {
        $takenDates = array_map(
            static fn (FinanceEntry $entry): string => $entry->date->format('Y-m-d'),
            $this->financeEntryRepository->findByRecurrenceAfter($recurrence, $after),
        );

        return $this->recurrenceEntryGenerator->generateMissingEntriesAfter(
            $recurrence,
            $recurrence->scope === FinanceEntryScope::Personal ? $ownerMembership : null,
            $after,
            $takenDates,
        );
    }

    /**
     * Pushes the recurrence's values back onto the forecasts it still owns, and reports how many actually
     * moved so the band is told the truth rather than just "enregistrée".
     *
     * An entry is left exactly as it is when it already matches, which is what makes an edit that changes
     * nothing on the recurrence change nothing on the entries.
     */
    private function syncFutureForecasts(FinanceRecurrence $recurrence, BandSpaceMembership $ownerMembership, DateTimeInterface $now): int
    {
        $updatedEntryCount = 0;

        foreach ($this->financeEntryRepository->findPlannedByRecurrenceAfter($recurrence, $now) as $entry) {
            $member = $recurrence->scope === FinanceEntryScope::Personal
                ? $entry->member ?? $ownerMembership
                : null;

            if (
                $entry->label === $recurrence->label
                && $entry->type === $recurrence->type
                && $entry->amount === $recurrence->amount
                && $entry->amountMin === null
                && $entry->amountMax === null
                && $entry->scope === $recurrence->scope
                && $entry->member === $member
            ) {
                continue;
            }

            $entry->label = $recurrence->label;
            $entry->type = $recurrence->type;
            $entry->amount = $recurrence->amount;
            // A forecast owned by a recurrence carries the recurrence's flat amount, never an estimate
            // range: leaving one behind would show a 300-400 bracket next to a 350 amount.
            $entry->amountMin = null;
            $entry->amountMax = null;
            $entry->scope = $recurrence->scope;
            $entry->member = $member;
            $entry->updateDatetime = new DateTime();

            ++$updatedEntryCount;
        }

        return $updatedEntryCount;
    }

    private function recordChanges(
        FinanceRecurrence $recurrence,
        User $user,
        string $oldLabel,
        FinanceEntryType $oldType,
        int $oldAmount,
        FinanceEntryScope $oldScope,
        bool $oldIsActive,
        bool $endDateChanged,
        string $oldEndDateString,
        string $newEndDateString,
    ): void {
        $bandSpace = $recurrence->category->bandSpace;

        if ($oldIsActive !== $recurrence->isActive) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Finance,
                type: $recurrence->isActive
                    ? BandSpaceFinanceActivityType::RecurrenceStarted
                    : BandSpaceFinanceActivityType::RecurrenceStopped,
                resourceId: $recurrence->id,
                actor: $user,
            );
        }

        if ($endDateChanged) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Finance,
                type: BandSpaceFinanceActivityType::RecurrenceEndDateChanged,
                resourceId: $recurrence->id,
                actor: $user,
                payload: ['from' => $oldEndDateString, 'to' => $newEndDateString],
            );
        }

        $changedFields = [];
        if ($oldLabel !== $recurrence->label) {
            $changedFields[] = 'label';
        }
        if ($oldType !== $recurrence->type) {
            $changedFields[] = 'type';
        }
        if ($oldAmount !== $recurrence->amount) {
            $changedFields[] = 'amount';
        }
        if ($oldScope !== $recurrence->scope) {
            $changedFields[] = 'scope';
        }

        if ($changedFields !== []) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Finance,
                type: BandSpaceFinanceActivityType::RecurrenceUpdated,
                resourceId: $recurrence->id,
                actor: $user,
                payload: ['changed_fields' => $changedFields],
            );
        }
    }
}
