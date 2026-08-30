<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Service\BandSpace\File\BandSpaceFileArchiver;
use App\Service\BandSpace\File\BandSpaceFileAttachmentLabels;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Moves a selection of files to the trash in one call.
 *
 * Both guards run over the whole selection before anything is written, for the reason the folder
 * cascade documents: the batch is one transaction, so a single file in the way takes the selection
 * with it, and a member who ticked twelve rows can only act on that if the answer says which ones.
 */
readonly class BandSpaceFileBulkDeleteProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private BandSpaceFileArchiver $fileArchiver,
    ) {
    }

    /**
     * @param string[] $fileIds
     */
    public function delete(BandSpace $bandSpace, BandSpaceMembership $membership, array $fileIds, User $user): void
    {
        if (count($fileIds) === 0) {
            return;
        }

        // Already trashed rows are not deletable, exactly as the single file endpoint 404s on one:
        // the trash is a separate listing with its own actions, so such an id is not in this list.
        $files = array_values(array_filter(
            $this->fileRepository->findByIdsAndBandSpace($fileIds, $bandSpace),
            static fn (BandSpaceFile $file): bool => !$file->isArchived(),
        ));

        $this->assertEveryFileWasFound($fileIds, $files);

        if ($membership->role !== Role::Admin) {
            $this->assertEveryFileIsOwnedBy($files, $user);
        }

        $this->assertNoAttachedFile($files);

        $this->entityManager->wrapInTransaction(function () use ($files, $bandSpace, $user): void {
            $this->fileArchiver->archive($files, $bandSpace, $user);
        });
    }

    /**
     * @param string[] $fileIds
     * @param BandSpaceFile[] $files
     */
    private function assertEveryFileWasFound(array $fileIds, array $files): void
    {
        $foundIds = array_map(static fn (BandSpaceFile $file): string => (string) $file->id, $files);
        $missing = array_diff($fileIds, $foundIds);

        if (count($missing) > 0) {
            throw new BadRequestHttpException(sprintf('Fichier %s introuvable dans ce Band Space', reset($missing)));
        }
    }

    /**
     * @param BandSpaceFile[] $files
     */
    private function assertEveryFileIsOwnedBy(array $files, User $user): void
    {
        $userId = (string) $user->id;
        $foreign = array_filter(
            $files,
            static fn (BandSpaceFile $file): bool => (string) $file->createdBy->id !== $userId,
        );

        if ($foreign === []) {
            return;
        }

        throw new AccessDeniedHttpException(sprintf(
            'Seul le créateur ou un administrateur peut supprimer ces fichiers : %s',
            implode(', ', array_map(static fn (BandSpaceFile $file): string => $file->originalName, $foreign)),
        ));
    }

    /**
     * Refuses the batch when it would trash a file some task, note or finance entry still points at,
     * which is what the single file endpoint and the folder cascade both already do.
     *
     * @param BandSpaceFile[] $files
     */
    private function assertNoAttachedFile(array $files): void
    {
        $sourceTypesByFile = $this->attachmentRepository->findSourceTypesByFileIds(
            array_map(static fn (BandSpaceFile $file): string => (string) $file->id, $files),
        );

        if (count($sourceTypesByFile) === 0) {
            return;
        }

        // Sorted because the sentence is asserted verbatim: without it the list follows whatever order
        // the database returned the attachment rows in.
        $sourceTypes = array_merge(...array_values($sourceTypesByFile));
        sort($sourceTypes);
        $sources = BandSpaceFileAttachmentLabels::describe($sourceTypes);

        throw new UnprocessableEntityHttpException(count($sourceTypesByFile) === 1
            ? sprintf("1 fichier sélectionné est attaché à %s. Détachez-le d'abord depuis la ressource concernée.", $sources)
            : sprintf(
                "%d fichiers sélectionnés sont attachés à %s. Détachez-les d'abord depuis les ressources concernées.",
                count($sourceTypesByFile),
                $sources,
            ));
    }
}
