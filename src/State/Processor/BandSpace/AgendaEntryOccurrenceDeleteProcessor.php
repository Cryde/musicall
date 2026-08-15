<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaEntryResource;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\AgendaEntryException;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceAgendaActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaEntryExceptionRepository;
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
 * DELETE /band_spaces/{bandSpaceId}/agenda-entries/{id}/occurrences/{occurrenceDate}
 *
 * Cancels a single occurrence of a recurring agenda entry by creating an
 * AgendaEntryException row. Idempotent: a duplicate call on the same date
 * returns 204 without creating a second row.
 *
 * @implements ProcessorInterface<AgendaEntryResource, void>
 */
readonly class AgendaEntryOccurrenceDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private AgendaEntryRepository $agendaEntryRepository,
        private AgendaEntryExceptionRepository $exceptionRepository,
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

        $existing = $this->exceptionRepository->findOneByEntryAndDate($entry, $occurrenceDate);
        if ($existing instanceof AgendaEntryException) {
            return;
        }

        // Cancelling the last occurrence would leave a series that expands to nothing: it renders
        // nowhere, and the expanded agenda is the only place it can be reached from, so it could
        // never be edited or deleted again. Remove it outright instead, the way truncating a series
        // before its first occurrence already does. The cancellation itself is not written: it
        // would be cascaded away with the entry on the same flush.
        if (!$this->agendaSeriesReconciler->hasLiveOccurrence($entry, $occurrenceDate)) {
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

        $exception = new AgendaEntryException();
        $exception->agendaEntry = $entry;
        $exception->occurrenceDate = $occurrenceDate;

        $this->entityManager->persist($exception);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Agenda,
            type: BandSpaceAgendaActivityType::OccurrenceCancelled,
            resourceId: $entry->id,
            actor: $user,
            payload: [
                'title' => $entry->title,
                'occurrence_date' => $occurrenceDate->format('Y-m-d'),
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
