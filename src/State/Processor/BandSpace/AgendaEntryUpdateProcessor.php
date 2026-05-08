<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaEntryResource;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\AgendaEntryBuilder;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->agendaEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$entry) {
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
            try {
                $entry->eventDatetime = new DateTimeImmutable($data->eventDatetime);
            } catch (\Exception) {
                throw new BadRequestHttpException('Date et heure invalides');
            }
        }

        if (array_key_exists('end_datetime', $payload) || array_key_exists('endDatetime', $payload)) {
            if ($data->endDatetime === null) {
                $entry->endDatetime = null;
            } else {
                try {
                    $entry->endDatetime = new DateTimeImmutable($data->endDatetime);
                } catch (\Exception) {
                    throw new BadRequestHttpException('Date de fin invalide');
                }
            }
        }

        if (array_key_exists('is_all_day', $payload) || array_key_exists('isAllDay', $payload)) {
            $entry->isAllDay = (bool) $data->isAllDay;
        }

        if ($entry->isAllDay) {
            $entry->eventDatetime = new DateTimeImmutable($entry->eventDatetime->format('Y-m-d') . 'T00:00:00+00:00');
            $entry->endDatetime = $entry->endDatetime !== null
                ? new DateTimeImmutable($entry->endDatetime->format('Y-m-d') . 'T00:00:00+00:00')
                : null;
        }

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
                type: 'title_changed',
                resourceId: $entry->id,
                actor: $user,
                payload: ['from' => $oldTitle, 'to' => $entry->title],
            );
        }

        if (($oldDescription ?? '') !== ($entry->description ?? '')) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->bandSpace,
                module: BandSpaceModule::Agenda,
                type: 'description_changed',
                resourceId: $entry->id,
                actor: $user,
            );
        }

        if ($oldLocation !== $entry->location) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->bandSpace,
                module: BandSpaceModule::Agenda,
                type: 'location_changed',
                resourceId: $entry->id,
                actor: $user,
                payload: ['from' => $oldLocation, 'to' => $entry->location],
            );
        }

        if ($oldEventDatetime->getTimestamp() !== $entry->eventDatetime->getTimestamp()) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->bandSpace,
                module: BandSpaceModule::Agenda,
                type: 'event_datetime_changed',
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
                type: 'end_datetime_changed',
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
                type: 'is_all_day_changed',
                resourceId: $entry->id,
                actor: $user,
                payload: ['from' => $oldIsAllDay, 'to' => $entry->isAllDay],
            );
        }
    }
}
