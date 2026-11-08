<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\ApiResource\BandSpace\BandSpaceActivityResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\BandSpaceActivityResourceKind;
use App\Enum\BandSpace\BandSpaceAgendaActivityType;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceFinanceActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceNoteActivityType;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Enum\BandSpace\BandSpaceTaskActivityType;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\LyricsRepository;
use App\Repository\BandSpace\SetlistRepository;
use App\Repository\BandSpace\SongRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Repository\BandSpace\TechRiderRepository;

/**
 * Says whether the item each activity line names can still be opened (#1354), so a line written before
 * its item was deleted stops linking to it. One query per kind of item on the page, never one per line.
 */
readonly class BandSpaceActivityAvailability
{
    public function __construct(
        private TaskRepository $taskRepository,
        private BandSpaceNoteRepository $noteRepository,
        private AgendaEntryRepository $agendaEntryRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private BandSpaceFileRepository $fileRepository,
        private SetlistRepository $setlistRepository,
        private SongRepository $songRepository,
        private LyricsRepository $lyricsRepository,
        private TechRiderRepository $techRiderRepository,
    ) {
    }

    /**
     * @param BandSpaceActivityResource[] $activities
     */
    public function fill(array $activities, BandSpace $bandSpace, BandSpaceMembership $viewer): void
    {
        $kinds = [];
        $idsByKind = [];
        foreach ($activities as $index => $activity) {
            $kind = $activity->resourceId !== null ? self::kindOf($activity->module, $activity->type) : null;
            $kinds[$index] = $kind;
            if ($kind instanceof BandSpaceActivityResourceKind) {
                $idsByKind[$kind->value][] = (string) $activity->resourceId;
            }
        }

        $openable = [];
        foreach ($idsByKind as $kindValue => $ids) {
            $found = $this->findOpenable(BandSpaceActivityResourceKind::from($kindValue), $bandSpace, $viewer, array_values(array_unique($ids)));
            // A GUID column compares case-insensitively, so the id read back may not be spelt like the stored one.
            $openable[$kindValue] = array_fill_keys(array_map(mb_strtolower(...), $found), true);
        }

        foreach ($activities as $index => $activity) {
            $kind = $kinds[$index];
            $activity->resourceAvailable = $kind instanceof BandSpaceActivityResourceKind
                ? isset($openable[$kind->value][mb_strtolower((string) $activity->resourceId)])
                : null;
        }
    }

    /** A module or a type this code does not know names nothing it can check, so the line says null. */
    private static function kindOf(string $module, string $type): ?BandSpaceActivityResourceKind
    {
        return match (BandSpaceModule::tryFrom($module)) {
            BandSpaceModule::Task => BandSpaceTaskActivityType::tryFrom($type)?->resourceKind(),
            BandSpaceModule::Notes => BandSpaceNoteActivityType::tryFrom($type)?->resourceKind(),
            BandSpaceModule::Agenda => BandSpaceAgendaActivityType::tryFrom($type)?->resourceKind(),
            BandSpaceModule::Finance => BandSpaceFinanceActivityType::tryFrom($type)?->resourceKind(),
            // A folder line falls through to null: a folder has no screen of its own.
            BandSpaceModule::File => BandSpaceFileActivityType::tryFrom($type)?->resourceKind(),
            BandSpaceModule::Setlist => BandSpaceSetlistActivityType::tryFrom($type)?->resourceKind(),
            BandSpaceModule::Rider => BandSpaceRiderActivityType::tryFrom($type)?->resourceKind(),
            // The space, a member or an invitation: none has a screen of its own.
            BandSpaceModule::Settings, null => null,
        };
    }

    /**
     * @param string[] $ids
     *
     * @return string[]
     */
    private function findOpenable(BandSpaceActivityResourceKind $kind, BandSpace $bandSpace, BandSpaceMembership $viewer, array $ids): array
    {
        return match ($kind) {
            BandSpaceActivityResourceKind::Task => $this->taskRepository->findIdsStillOpenable($bandSpace, $ids),
            BandSpaceActivityResourceKind::Note => $this->noteRepository->findIdsStillOpenable($bandSpace, $ids),
            BandSpaceActivityResourceKind::AgendaEntry => $this->agendaEntryRepository->findIdsStillOpenable($bandSpace, $ids),
            BandSpaceActivityResourceKind::FinanceEntry => $this->financeEntryRepository->findIdsStillOpenable($bandSpace, $viewer, $ids),
            BandSpaceActivityResourceKind::File => $this->fileRepository->findIdsStillOpenable($bandSpace, $ids),
            BandSpaceActivityResourceKind::Setlist => $this->setlistRepository->findIdsStillOpenable($bandSpace, $ids),
            BandSpaceActivityResourceKind::Song => $this->songRepository->findIdsStillOpenable($bandSpace, $ids),
            BandSpaceActivityResourceKind::Lyrics => $this->lyricsRepository->findIdsStillOpenable($bandSpace, $ids),
            BandSpaceActivityResourceKind::Rider => $this->techRiderRepository->findIdsStillOpenable($bandSpace, $ids),
        };
    }
}
