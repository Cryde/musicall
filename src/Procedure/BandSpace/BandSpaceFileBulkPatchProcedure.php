<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Applies one merge-patch to a selection of files, which today means moving them to a folder.
 *
 * Delegates per file to BandSpaceFileUpdateProcedure, the same procedure the single file PATCH runs,
 * so folder validation and the Moved activity stay in one place rather than being restated here.
 *
 * No ownership check, deliberately: the single file PATCH has none either, so moving is member level
 * while deleting is creator-or-admin. Adding one here would make the two disagree.
 *
 * Note that "an attached file cannot sit at the root" is a client side rule only, enforced by
 * canDrop() and the move dialog. The batch inherits the server's actual behaviour, which is to allow
 * it, rather than inventing a refusal the single file path does not have.
 */
readonly class BandSpaceFileBulkPatchProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileUpdateProcedure $fileUpdateProcedure,
    ) {
    }

    /**
     * @param string[] $fileIds
     * @param array<string, mixed> $patchPayload raw merge-patch fields applied to every file
     */
    public function patch(BandSpace $bandSpace, array $fileIds, array $patchPayload, User $user): void
    {
        if (count($fileIds) === 0 || count($patchPayload) === 0) {
            return;
        }

        // A trashed file is not in the listing the selection came from, and the single file PATCH 404s
        // on one, so an id pointing at the trash counts as missing here.
        $files = array_values(array_filter(
            $this->fileRepository->findByIdsAndBandSpace($fileIds, $bandSpace),
            static fn (BandSpaceFile $file): bool => !$file->isArchived(),
        ));

        $foundIds = array_map(static fn (BandSpaceFile $file): string => (string) $file->id, $files);
        $missing = array_diff($fileIds, $foundIds);
        if (count($missing) > 0) {
            throw new BadRequestHttpException(sprintf('Fichier %s introuvable dans ce Band Space', reset($missing)));
        }

        // Resolved once: the destination is the same for every file, so leaving the lookup inside the
        // per file path would repeat one identical query per row, up to the 2000 the cap allows.
        // It also refuses an unknown folder before anything is written.
        $folder = $this->fileUpdateProcedure->resolveFolder($patchPayload['folder_id'] ?? null, $bandSpace);

        $this->entityManager->wrapInTransaction(function () use ($files, $folder, $user): void {
            // One instance for the batch, so every file it moves carries the same timestamp. Mutable
            // on purpose: updateDatetime is a DATETIME_MUTABLE column and DBAL's DateTimeType accepts
            // only null or DateTime, so the DateTimeImmutable the archiver uses would throw here.
            $movedAt = new DateTime();

            foreach ($files as $file) {
                if ($this->fileUpdateProcedure->moveToFolder($file, $folder, $user)) {
                    $file->updateDatetime = $movedAt;
                }
            }
            // wrapInTransaction flushes once at the end, rather than the single file path's flush per
            // call, which at this size would be a changeset computation per row.
        });
    }
}
