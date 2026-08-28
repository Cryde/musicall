<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaEntryCreate;
use App\ApiResource\BandSpace\AgendaEntryResource;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\User;
use App\Enum\BandSpace\AgendaRecurrenceFrequency;
use App\Enum\BandSpace\AgendaRecurrenceMonthlyMode;
use App\Enum\BandSpace\BandSpaceAgendaActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Event\BandSpaceAgendaEntryCreatedEvent;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\AgendaEntryBuilder;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<AgendaEntryCreate, AgendaEntryResource>
 */
readonly class AgendaEntryCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private AgendaEntryBuilder $agendaEntryBuilder,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param AgendaEntryCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgendaEntryResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $eventDatetime = $data->eventDatetime;
        $endDatetime = $data->endDatetime;

        if ($data->isAllDay) {
            // A whole day is a date, not an instant, and the date is the caller's own: read off the
            // wall clock they sent, before any conversion, then pinned to midnight UTC. Converting
            // first would move 2026-08-25T00:00+02:00 to the 24th.
            $eventDatetime = new DateTimeImmutable($eventDatetime->format('Y-m-d') . 'T00:00:00+00:00');
            $endDatetime = $endDatetime instanceof \DateTimeImmutable
                ? new DateTimeImmutable($endDatetime->format('Y-m-d') . 'T00:00:00+00:00')
                : null;
        } else {
            // AgendaEntry::$eventDatetime is mapped DATETIME_IMMUTABLE, Doctrine's `datetime`, which
            // persists the object's wall clock and drops its timezone. So the column can only hold UTC
            // correctly, and an offset the caller sent has to be converted here or the instant moves:
            // 20:00+02:00 would be stored as 20:00 and read back as 20:00Z, two hours late.
            //
            // The reading half of that rests on the runtime's own timezone, since Doctrine hydrates the
            // column with date_default_timezone_get() and nothing in this repository pins it. The two
            // tiers disagreed on it once already, which is what #888 was about.
            $eventDatetime = $eventDatetime->setTimezone(new DateTimeZone('UTC'));
            $endDatetime = $endDatetime?->setTimezone(new DateTimeZone('UTC'));
        }

        $entry = new AgendaEntry();
        $entry->bandSpace = $bandSpace;
        $entry->creator = $user;
        $entry->title = $data->title;
        $entry->description = $data->description;
        $entry->location = $data->location;
        $entry->eventDatetime = $eventDatetime;
        $entry->endDatetime = $endDatetime;
        $entry->isAllDay = $data->isAllDay;

        if ($data->recurrenceFrequency !== null && $data->recurrenceFrequency !== '') {
            // ValidRecurrence has already accepted the inputs here; tryFrom + parse can't fail.
            $entry->recurrenceFrequency = AgendaRecurrenceFrequency::from($data->recurrenceFrequency);
            $entry->recurrenceUntilDate = new DateTimeImmutable((string) $data->recurrenceUntilDate);
            if ($entry->recurrenceFrequency === AgendaRecurrenceFrequency::Monthly) {
                $entry->recurrenceMonthlyMode = AgendaRecurrenceMonthlyMode::from((string) $data->recurrenceMonthlyMode);
            }
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Agenda,
            type: BandSpaceAgendaActivityType::EntryCreated,
            resourceId: $entry->id,
            actor: $user,
            payload: ['title' => $entry->title],
        );
        $this->entityManager->flush();

        // Best-effort notification dispatched after the commit (epic #689 contract).
        $this->eventDispatcher->dispatch(new BandSpaceAgendaEntryCreatedEvent($entry, $user));

        return $this->agendaEntryBuilder->buildItem($entry);
    }
}
