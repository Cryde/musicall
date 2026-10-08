<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\BandSpace;
use App\Repository\BandSpace\AgendaEntryRepository;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Resolves "this date of that entry" for the availability endpoints (#1000), so an answer can only
 * ever be filed under an occurrence the entry really has.
 */
readonly class AgendaOccurrenceLocator
{
    public function __construct(
        private AgendaEntryRepository $agendaEntryRepository,
        private AgendaAggregator $agendaAggregator,
    ) {
    }

    /**
     * @return array{AgendaEntry, string, DateTimeImmutable} the entry, its occurrence date ('Y-m-d') and that occurrence's start
     */
    public function locate(BandSpace $bandSpace, string $entryId, ?string $occurrenceDate): array
    {
        $entry = $this->agendaEntryRepository->findOneByIdAndBandSpace($entryId, $bandSpace);
        if (!$entry instanceof AgendaEntry) {
            throw new NotFoundHttpException('Événement introuvable');
        }

        if ($occurrenceDate === null || $occurrenceDate === '') {
            if ($entry->recurrenceFrequency !== null) {
                throw new UnprocessableEntityHttpException('Précisez la date de l\'occurrence');
            }
            $occurrenceDate = AgendaAggregator::occurrenceDateOf($entry->eventDatetime);
        }

        $occurrenceStart = $this->agendaAggregator->occurrenceStartOn($entry, $occurrenceDate);
        if (!$occurrenceStart instanceof DateTimeImmutable) {
            throw new NotFoundHttpException('Cet événement n\'a pas lieu à cette date');
        }

        return [$entry, $occurrenceDate, $occurrenceStart];
    }

    /** Same UTC key as the occurrence date, so "today" and "that date" are compared on one calendar. */
    public static function isPast(string $occurrenceDate): bool
    {
        return $occurrenceDate < (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
    }
}
