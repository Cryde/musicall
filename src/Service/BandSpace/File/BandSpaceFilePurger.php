<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFile;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Destroys a file for good: every version's stored object, every version row, then the file row.
 *
 * Shared by app:band-space:purge, which sweeps files past their grace period, and by the trash's
 * "delete permanently" endpoint, which does the same thing on demand. One implementation because
 * getting this wrong silently orphans objects in the bucket, and an orphan is invisible: nothing in
 * the application ever points at it again.
 */
readonly class BandSpaceFilePurger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceFileVersionRepository $bandSpaceFileVersionRepository,
    ) {
    }

    public function purge(BandSpaceFile $file): void
    {
        // Detach the pointer first. The database would cope on its own (the FK is ON DELETE SET NULL),
        // but this keeps Doctrine's in-memory graph honest: no managed entity is left pointing at a row
        // that has just been removed.
        $file->currentVersion = null;
        $this->entityManager->flush();

        // No explicit storage call here: VichUploader listens on the removal of an uploadable entity and
        // deletes the object itself (delete_on_remove, on by default). That is the whole reason the
        // versions go through the ORM one by one instead of dying by FK cascade. Replacing this loop with
        // a bulk DQL delete would leave every object behind, with nothing left pointing at them.
        foreach ($this->bandSpaceFileVersionRepository->findByFileNewestFirst($file) as $version) {
            $this->entityManager->remove($version);
        }
        $this->entityManager->flush();

        $this->entityManager->remove($file);
        $this->entityManager->flush();
    }
}
