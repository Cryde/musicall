<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
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
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileSourceDetacher;
use App\Service\BandSpace\RecurrenceEntryGenerator;
use App\Service\Builder\BandSpace\FinanceRecurrenceBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceRecurrenceResource, FinanceRecurrenceResource>
 */
readonly class FinanceRecurrenceUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
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
        if (!$recurrence instanceof \App\Entity\BandSpace\FinanceRecurrence) {
            throw new NotFoundHttpException('Récurrence introuvable');
        }

        $requestPayload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $oldLabel = $recurrence->label;
        $oldType = $recurrence->type;
        $oldAmount = $recurrence->amount;
        $oldScope = $recurrence->scope;
        $oldInterval = $recurrence->interval;
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

        if (array_key_exists('interval', $requestPayload)) {
            $recurrence->interval = RecurrenceInterval::from($data->interval);
        }

        if (array_key_exists('is_active', $requestPayload)) {
            $recurrence->isActive = $data->isActive;
        }

        // From here to the flush runs in one transaction: shortening the end date drops the entries
        // past it through a bulk DQL delete that reaches the database immediately, while the files
        // detached alongside them wait for the flush. Unwrapped, a flush that failed would leave the
        // entries gone and their attachments orphaned, which is the leak this change exists to stop.
        $this->entityManager->wrapInTransaction(function () use (
            $recurrence,
            $data,
            $requestPayload,
            $bandSpace,
            $user,
            $currentMembership,
            $oldLabel,
            $oldType,
            $oldAmount,
            $oldScope,
            $oldInterval,
            $oldIsActive,
        ): void {
            $endDateChanged = false;
            $oldEndDateString = $recurrence->endDate->format('Y-m-d');
            $newEndDateString = $oldEndDateString;
            if (array_key_exists('end_date', $requestPayload)) {
                $oldEndDate = $recurrence->endDate;
                $newEndDate = new DateTime($data->endDate);

                $recurrence->endDate = $newEndDate;
                $newEndDateString = $newEndDate->format('Y-m-d');
                $endDateChanged = $oldEndDateString !== $newEndDateString;

                if ($newEndDate > $oldEndDate) {
                    $fromDate = $this->nextIntervalDate($oldEndDate, $recurrence->interval);
                    $member = $recurrence->scope === FinanceEntryScope::Personal ? $currentMembership : null;
                    $entries = $this->recurrenceEntryGenerator->generateEntries($recurrence, $member, $fromDate);

                    foreach ($entries as $entry) {
                        $this->entityManager->persist($entry);
                    }
                } elseif ($newEndDate < $oldEndDate) {
                    $this->fileSourceDetacher->detachDeletedSources(
                        $bandSpace,
                        'finance',
                        $this->financeEntryRepository->findPlannedLabelsByRecurrence($recurrence, $newEndDate),
                        $user,
                    );
                    $this->financeEntryRepository->deletePlannedByRecurrence($recurrence, $newEndDate);
                }
            }

            $recurrence->updateDatetime = new DateTime();

            $this->recordChanges(
                $recurrence,
                $user,
                $oldLabel,
                $oldType,
                $oldAmount,
                $oldScope,
                $oldInterval,
                $oldIsActive,
                $endDateChanged,
                $oldEndDateString,
                $newEndDateString,
            );
        });

        return $this->financeRecurrenceBuilder->buildItem($recurrence);
    }

    private function recordChanges(
        FinanceRecurrence $recurrence,
        User $user,
        string $oldLabel,
        FinanceEntryType $oldType,
        int $oldAmount,
        FinanceEntryScope $oldScope,
        RecurrenceInterval $oldInterval,
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
        if ($oldInterval !== $recurrence->interval) {
            $changedFields[] = 'interval';
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

    private function nextIntervalDate(\DateTimeInterface $date, RecurrenceInterval $interval): \DateTimeInterface
    {
        $next = DateTime::createFromInterface($date);

        return match ($interval) {
            RecurrenceInterval::Weekly => $next->modify('+7 days'),
            RecurrenceInterval::Monthly => $next->modify('+1 month'),
            RecurrenceInterval::Quarterly => $next->modify('+3 months'),
            RecurrenceInterval::Yearly => $next->modify('+12 months'),
        };
    }
}
