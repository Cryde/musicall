<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cuts the file attachments of a source that is being hard-deleted (#818).
 *
 * band_space_file_attachment.source_id is polymorphic and carries no foreign key, so nothing cascades
 * when a task, a note or a finance entry is removed. Left behind, the attachment row keeps the file
 * pinned forever: BandSpaceFileDeleteProcessor refuses to trash an attached file, and the detach
 * endpoint that would release it 404s because its source is gone. The file then eats quota for good.
 *
 * The file itself is deliberately kept. It is a first-class object of the library, with its own
 * versions, tags, folder and share links, and it may hang on several sources at once; destroying it
 * because one of them went away would be an unrequested deletion of somebody else's data. Instead it
 * becomes a plain unattached file, visible under the "Manuel" source filter and deletable through the
 * normal flow, and the band is told through a source_deleted entry in the file's activity feed.
 *
 * One implementation rather than one per delete processor: the rule (an attachment never outlives its
 * source, and the file feed always says so) is the same everywhere, and a source that forgets to apply
 * it produces a leak nothing in the application can undo.
 */
readonly class BandSpaceFileSourceDetacher
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private BandSpaceActivityRecorder $activityRecorder,
    ) {
    }

    /**
     * Call this before the source rows go, while their labels can still be read. Nothing is flushed
     * here: the removals join the caller's own unit of work so the source and its attachments disappear
     * together, or not at all.
     *
     * @param array<string, string> $labelsBySourceId id of every source about to be deleted, mapped to
     *                                                the name the activity feed should show for it
     * @param User                  $actor            required, not optional: BandSpaceFileActivityBuilder
     *                                                drops activities with no actor, so an anonymous
     *                                                entry here would leave the band unaware the file
     *                                                had been detached at all
     */
    public function detachDeletedSources(
        BandSpace $bandSpace,
        string $sourceType,
        array $labelsBySourceId,
        User $actor,
    ): void {
        if (count($labelsBySourceId) === 0) {
            return;
        }

        $attachments = $this->attachmentRepository->findBySource($sourceType, array_keys($labelsBySourceId));
        $now = new DateTime();

        foreach ($attachments as $attachment) {
            $file = $attachment->bandSpaceFile;
            $sourceId = (string) $attachment->sourceId;

            $this->entityManager->remove($attachment);
            $file->updateDatetime = $now;

            $this->activityRecorder->record(
                $bandSpace,
                BandSpaceModule::File,
                BandSpaceFileActivityType::SourceDeleted,
                resourceId: (string) $file->id,
                actor: $actor,
                payload: [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'source_label' => $labelsBySourceId[$sourceId] ?? null,
                ],
            );
        }
    }
}
