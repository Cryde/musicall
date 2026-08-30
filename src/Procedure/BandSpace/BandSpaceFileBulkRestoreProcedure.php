<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileQuotaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Brings a selection of files back out of the trash in one call.
 *
 * Mirrors BandSpaceFileRestoreProcessor refusal for refusal, with one difference that is the whole
 * point of doing it here: the quota is asserted once for the batch rather than once per file.
 */
readonly class BandSpaceFileBulkRestoreProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileVersionRepository $versionRepository,
        private BandSpaceFileQuotaService $quotaService,
        private BandSpaceActivityRecorder $activityRecorder,
    ) {
    }

    /**
     * @param string[] $fileIds
     */
    public function restore(BandSpace $bandSpace, BandSpaceMembership $membership, array $fileIds, User $user): void
    {
        if (count($fileIds) === 0) {
            return;
        }

        $files = $this->fileRepository->findByIdsAndBandSpace($fileIds, $bandSpace);

        $this->assertEveryFileWasFound($fileIds, $files);
        $this->assertEveryFileIsInTheTrash($files);

        if ($membership->role !== Role::Admin) {
            $this->assertEveryFileIsOwnedBy($files, $user);
        }

        // Once for the whole batch, never once per file. The quota counts live files only, so asserting
        // per file would weigh each one against a total none of its siblings had been added to yet and
        // let a selection walk the space past its limit one restore at a time.
        $this->quotaService->assertCanUpload(
            $bandSpace,
            $this->versionRepository->sumBytesByFileIds(
                array_map(static fn (BandSpaceFile $file): string => (string) $file->id, $files),
            ),
        );

        $this->entityManager->wrapInTransaction(function () use ($files, $bandSpace, $user): void {
            foreach ($files as $file) {
                // Only the archive flag comes off. Share links revoked on the way into the trash stay
                // revoked: a link the band watched disappear must not come back behind their back.
                $file->archiveDatetime = null;

                $this->activityRecorder->record(
                    $bandSpace,
                    BandSpaceModule::File,
                    BandSpaceFileActivityType::Restored,
                    resourceId: (string) $file->id,
                    actor: $user,
                    payload: ['original_name' => $file->originalName],
                );
            }
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
    private function assertEveryFileIsInTheTrash(array $files): void
    {
        $live = array_filter($files, static fn (BandSpaceFile $file): bool => !$file->isArchived());

        if ($live === []) {
            return;
        }

        throw new ConflictHttpException(sprintf(
            'Ces fichiers ne sont pas dans la corbeille : %s',
            implode(', ', array_map(static fn (BandSpaceFile $file): string => $file->originalName, $live)),
        ));
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
            'Seul le créateur ou un administrateur peut restaurer ces fichiers : %s',
            implode(', ', array_map(static fn (BandSpaceFile $file): string => $file->originalName, $foreign)),
        ));
    }
}
