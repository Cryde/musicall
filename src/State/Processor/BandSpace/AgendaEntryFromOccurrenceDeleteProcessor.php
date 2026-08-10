<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaEntryResource;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceAgendaActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\AgendaSeriesReconciler;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * DELETE /band_spaces/{bandSpaceId}/agenda-entries/{id}/from/{occurrenceDate}
 *
 * Truncates a recurring agenda entry's series at the day before the picked
 * occurrence. If the picked date is on or before the first occurrence, the
 * entire entry is removed (no orphan empty series).
 *
 * @implements ProcessorInterface<AgendaEntryResource, void>
 */
readonly class AgendaEntryFromOccurrenceDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private AgendaEntryRepository $agendaEntryRepository,
        private AgendaSeriesReconciler $agendaSeriesReconciler,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param AgendaEntryResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->agendaEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$entry instanceof AgendaEntry) {
            throw new NotFoundHttpException('Événement introuvable');
        }

        if ($entry->recurrenceFrequency === null) {
            throw new UnprocessableEntityHttpException('Cet événement n\'est pas récurrent');
        }

        // API Platform does not pass non-Link URI segments into $uriVariables;
        // Symfony's router does populate them as request attributes.
        $rawDate = (string) ($this->requestStack->getCurrentRequest()?->attributes->get('occurrenceDate') ?? '');
        $occurrenceDate = $this->parseDate($rawDate);
        $firstOccurrenceDate = new DateTimeImmutable($entry->eventDatetime->format('Y-m-d'));

        $newUntil = $occurrenceDate->modify('-1 day');
        if ($occurrenceDate > $firstOccurrenceDate) {
            $entry->recurrenceUntilDate = $newUntil;
            // The cancellations past the new horizon are now dead rows. Left behind, they would
            // silently re-apply themselves the day someone extends the series again.
            $this->agendaSeriesReconciler->dropExceptionsOutsideRule($entry);
        }

        // Either the picked date is on or before the first occurrence, or everything the truncated
        // series still produces has already been cancelled one occurrence at a time. Both leave a
        // series that expands to nothing, which renders nowhere and can no longer be reached from
        // the agenda, so the entry goes (this is the user-facing semantic of "and all the rest").
        if ($occurrenceDate <= $firstOccurrenceDate || !$this->agendaSeriesReconciler->hasLiveOccurrence($entry)) {
            $this->activityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Agenda,
                type: BandSpaceAgendaActivityType::EntryDeleted,
                resourceId: $entry->id,
                actor: $user,
                payload: ['title' => $entry->title],
            );

            $this->entityManager->remove($entry);
            $this->entityManager->flush();

            return;
        }

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Agenda,
            type: BandSpaceAgendaActivityType::SeriesTruncated,
            resourceId: $entry->id,
            actor: $user,
            payload: [
                'title' => $entry->title,
                'from_occurrence_date' => $occurrenceDate->format('Y-m-d'),
                'recurrence_until_date' => $newUntil->format('Y-m-d'),
            ],
        );

        $this->entityManager->flush();
    }

    private function parseDate(string $raw): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (!$parsed instanceof DateTimeImmutable || $parsed->format('Y-m-d') !== $raw) {
            throw new BadRequestHttpException('Date d\'occurrence invalide (format attendu: YYYY-MM-DD)');
        }

        return $parsed;
    }
}
