<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaEntryResource;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\User;
use App\Enum\BandSpace\AgendaRecurrenceFrequency;
use App\Enum\BandSpace\AgendaRecurrenceMonthlyMode;
use App\Enum\BandSpace\BandSpaceAgendaActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\AgendaSeriesReconciler;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\AgendaEntryBuilder;
use DateTimeImmutable;
use DateTimeZone;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<AgendaEntryResource, AgendaEntryResource>
 */
readonly class AgendaEntryUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private AgendaEntryRepository $agendaEntryRepository,
        private AgendaEntryBuilder $agendaEntryBuilder,
        private AgendaSeriesReconciler $agendaSeriesReconciler,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param AgendaEntryResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgendaEntryResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->agendaEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$entry instanceof \App\Entity\BandSpace\AgendaEntry) {
            throw new NotFoundHttpException('Événement introuvable');
        }

        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $oldTitle = $entry->title;
        $oldDescription = $entry->description;
        $oldLocation = $entry->location;
        $oldEventDatetime = $entry->eventDatetime;
        $oldEndDatetime = $entry->endDatetime;
        $oldIsAllDay = $entry->isAllDay;

        if (array_key_exists('title', $payload)) {
            $entry->title = $data->title;
        }

        if (array_key_exists('description', $payload)) {
            $entry->description = $data->description;
        }

        if (array_key_exists('location', $payload)) {
            $entry->location = $data->location;
        }

        if (array_key_exists('event_datetime', $payload) || array_key_exists('eventDatetime', $payload)) {
            $entry->eventDatetime = $data->eventDatetime;
        }

        if (array_key_exists('end_datetime', $payload) || array_key_exists('endDatetime', $payload)) {
            $entry->endDatetime = $data->endDatetime;
        }

        if (array_key_exists('is_all_day', $payload) || array_key_exists('isAllDay', $payload)) {
            $entry->isAllDay = (bool) $data->isAllDay;
        }

        if ($entry->isAllDay) {
            // The caller's own date, read before any conversion: see AgendaEntryCreateProcessor.
            $entry->eventDatetime = new DateTimeImmutable($entry->eventDatetime->format('Y-m-d') . 'T00:00:00+00:00');
            $entry->endDatetime = $entry->endDatetime instanceof \DateTimeImmutable
                ? new DateTimeImmutable($entry->endDatetime->format('Y-m-d') . 'T00:00:00+00:00')
                : null;
        } else {
            // The column holds a wall clock with no timezone, so anything not already UTC has to be
            // converted before it is written. A no-op for a value that came back from the database.
            $entry->eventDatetime = $entry->eventDatetime->setTimezone(new DateTimeZone('UTC'));
            $entry->endDatetime = $entry->endDatetime?->setTimezone(new DateTimeZone('UTC'));
        }

        // Each recurrence field accepts an independent PATCH. ValidRecurrence runs against
        // the merged DTO, so it sees the post-merge state regardless of which field(s) the
        // caller sent. Clearing the frequency cascades to the other two (the rule is gone).
        $hasFrequencyKey = array_key_exists('recurrence_frequency', $payload) || array_key_exists('recurrenceFrequency', $payload);
        $hasUntilKey = array_key_exists('recurrence_until_date', $payload) || array_key_exists('recurrenceUntilDate', $payload);
        $hasMonthlyModeKey = array_key_exists('recurrence_monthly_mode', $payload) || array_key_exists('recurrenceMonthlyMode', $payload);

        if ($hasFrequencyKey) {
            if ($data->recurrenceFrequency === null || $data->recurrenceFrequency === '') {
                $entry->recurrenceFrequency = null;
                $entry->recurrenceUntilDate = null;
                $entry->recurrenceMonthlyMode = null;
            } else {
                $entry->recurrenceFrequency = AgendaRecurrenceFrequency::from($data->recurrenceFrequency);
            }
        }

        if ($hasUntilKey && $entry->recurrenceFrequency !== null) {
            $entry->recurrenceUntilDate = $data->recurrenceUntilDate !== null && $data->recurrenceUntilDate !== ''
                ? new DateTimeImmutable($data->recurrenceUntilDate)
                : null;
        }

        if ($hasMonthlyModeKey) {
            if ($entry->recurrenceFrequency === AgendaRecurrenceFrequency::Monthly) {
                $entry->recurrenceMonthlyMode = $data->recurrenceMonthlyMode !== null && $data->recurrenceMonthlyMode !== ''
                    ? AgendaRecurrenceMonthlyMode::from($data->recurrenceMonthlyMode)
                    : null;
            } else {
                // Mode only has meaning for Monthly — keep it null on other frequencies.
                $entry->recurrenceMonthlyMode = null;
            }
        }

        // The anchor and the rule have just been rewritten, so cancellations may now point at dates
        // the series no longer has. Asked unconditionally rather than only when a recurrence field
        // was in the payload: a cancellation outside the rule is dead data whatever wrote it, and
        // the entries that carry no cancellation at all leave here without expanding anything.
        $this->agendaSeriesReconciler->dropExceptionsOutsideRule($entry);

        $this->recordChanges(
            $entry,
            $user,
            $oldTitle,
            $oldDescription,
            $oldLocation,
            $oldEventDatetime,
            $oldEndDatetime,
            $oldIsAllDay,
        );

        $this->entityManager->flush();

        return $this->agendaEntryBuilder->buildItem($entry);
    }

    private function recordChanges(
        AgendaEntry $entry,
        User $user,
        string $oldTitle,
        ?string $oldDescription,
        ?string $oldLocation,
        DateTimeImmutable $oldEventDatetime,
        ?DateTimeImmutable $oldEndDatetime,
        bool $oldIsAllDay,
    ): void {
        if ($oldTitle !== $entry->title) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->bandSpace,
                module: BandSpaceModule::Agenda,
                type: BandSpaceAgendaActivityType::TitleChanged,
                resourceId: $entry->id,
                actor: $user,
                payload: ['from' => $oldTitle, 'to' => $entry->title],
            );
        }

        if (($oldDescription ?? '') !== ($entry->description ?? '')) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->bandSpace,
                module: BandSpaceModule::Agenda,
                type: BandSpaceAgendaActivityType::DescriptionChanged,
                resourceId: $entry->id,
                actor: $user,
            );
        }

        if ($oldLocation !== $entry->location) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->bandSpace,
                module: BandSpaceModule::Agenda,
                type: BandSpaceAgendaActivityType::LocationChanged,
                resourceId: $entry->id,
                actor: $user,
                payload: ['from' => $oldLocation, 'to' => $entry->location],
            );
        }

        if ($oldEventDatetime->getTimestamp() !== $entry->eventDatetime->getTimestamp()) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->bandSpace,
                module: BandSpaceModule::Agenda,
                type: BandSpaceAgendaActivityType::EventDatetimeChanged,
                resourceId: $entry->id,
                actor: $user,
                payload: [
                    'from' => $oldEventDatetime->format(DateTimeInterface::ATOM),
                    'to' => $entry->eventDatetime->format(DateTimeInterface::ATOM),
                ],
            );
        }

        $oldEndTs = $oldEndDatetime?->getTimestamp();
        $newEndTs = $entry->endDatetime?->getTimestamp();
        if ($oldEndTs !== $newEndTs) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->bandSpace,
                module: BandSpaceModule::Agenda,
                type: BandSpaceAgendaActivityType::EndDatetimeChanged,
                resourceId: $entry->id,
                actor: $user,
                payload: [
                    'from' => $oldEndDatetime?->format(DateTimeInterface::ATOM),
                    'to' => $entry->endDatetime?->format(DateTimeInterface::ATOM),
                ],
            );
        }

        if ($oldIsAllDay !== $entry->isAllDay) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->bandSpace,
                module: BandSpaceModule::Agenda,
                type: BandSpaceAgendaActivityType::IsAllDayChanged,
                resourceId: $entry->id,
                actor: $user,
                payload: ['from' => $oldIsAllDay, 'to' => $entry->isAllDay],
            );
        }
    }
}
