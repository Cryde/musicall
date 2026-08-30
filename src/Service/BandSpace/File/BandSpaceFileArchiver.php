<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTimeImmutable;

/**
 * Moves a set of files to the trash, the way the single file endpoint does it one at a time.
 *
 * Two paths trash several files at once, the folder cascade and the bulk selection, and they have to
 * agree on what trashing means: the archive flag, one Archived activity per file, and the share links
 * revoked. The folder cascade owning that loop privately is how the single file path and the cascade
 * came to disagree in the first place (#823), so it lives here instead.
 *
 * Deliberately does not flush. Both callers wrap the write in their own transaction and have more to
 * persist than the files.
 */
readonly class BandSpaceFileArchiver
{
    public function __construct(
        private BandSpaceFolderRepository $folderRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private BandSpaceFileShareRevoker $shareRevoker,
    ) {
    }

    /**
     * Each file keeps the path it was archived from in its Archived activity. The folder cascade needs
     * it because band_space_file.folder_id is ON DELETE SET NULL and the folder rows are about to go,
     * so without it a restore drops the file at the root with nothing left saying where it lived.
     *
     * Paths are memoised per folder. Walking a chain pulls each ancestor in once and the identity map
     * serves every later walk, which caps the extra queries at the number of distinct folders in the
     * set plus the chain above them, never at the number of files.
     *
     * @param BandSpaceFile[] $files
     */
    public function archive(array $files, BandSpace $bandSpace, User $actor): void
    {
        if (count($files) === 0) {
            return;
        }

        $archivedAt = new DateTimeImmutable();
        $pathByFolderId = [];

        foreach ($files as $file) {
            $file->archiveDatetime = $archivedAt;

            $folderId = (string) $file->folder?->id;
            $pathByFolderId[$folderId] ??= implode(
                ' / ',
                array_column($this->folderRepository->buildPath($file->folder), 'name'),
            );

            $this->activityRecorder->record(
                $bandSpace,
                BandSpaceModule::File,
                BandSpaceFileActivityType::Archived,
                resourceId: (string) $file->id,
                actor: $actor,
                payload: [
                    'original_name' => $file->originalName,
                    'folder_path' => $pathByFolderId[$folderId],
                ],
            );
        }

        $this->shareRevoker->revokeForArchivedFiles(
            $bandSpace,
            array_map(static fn (BandSpaceFile $file): string => (string) $file->id, $files),
            $actor,
        );
    }
}
