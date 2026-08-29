<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFolderResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFolder;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceFolderActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileAttachmentLabels;
use App\Service\BandSpace\File\BandSpaceFileShareRevoker;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFolderResource, void>
 */
readonly class BandSpaceFolderDeleteProcessor implements ProcessorInterface
{
    private const string STRATEGY_MOVE_TO_ROOT = 'move_to_root';
    private const string STRATEGY_CASCADE = 'cascade';

    /**
     * Guard rail on the cascade, not a workflow limit.
     *
     * Archiving is per file (each one needs its own Archived activity and its share links revoked), so
     * the request writes roughly two statements per file. Nothing caps how many files a space holds:
     * BAND_SPACE_FILE_QUOTA_BYTES caps bytes, 5 GiB by default, so a band filling it with 2 MiB photos
     * rather than the audio the module is built around tops out around 2500 files for the whole space.
     * A single subtree above this limit is therefore already pathological, while the limit sits above
     * what one folder of a realistic band space can hold.
     *
     * Refusing beats letting it run. The cascade is one transaction, so a request that times out rolls
     * back and leaves the admin with a 504 explaining nothing. This way they get a sentence telling
     * them what to do instead.
     *
     * Measured at the limit (BandSpaceFolderDeleteTest, 2000 files): about 6 to 7 seconds and 70 MB
     * over an idle request. Slow, but it completes, and it is inside the usual PHP-FPM and nginx
     * budgets. That cost is the reason the limit sits here rather than higher.
     */
    private const int MAX_CASCADE_FILES = 2000;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFolderRepository $folderRepository,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private BandSpaceFileShareRevoker $shareRevoker,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [, $membership] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $folder = $this->folderRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $membership->bandSpace);
        if (!$folder instanceof BandSpaceFolder) {
            throw new NotFoundHttpException('Dossier introuvable');
        }

        $strategy = $this->requestStack->getCurrentRequest()?->query->getString('strategy') ?: self::STRATEGY_MOVE_TO_ROOT;

        if (!in_array($strategy, [self::STRATEGY_MOVE_TO_ROOT, self::STRATEGY_CASCADE], true)) {
            throw new BadRequestHttpException(sprintf('Stratégie de suppression invalide : %s', $strategy));
        }

        if ($strategy === self::STRATEGY_CASCADE && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul un administrateur peut supprimer un dossier en cascade');
        }

        $isOwner = $folder->createdBy instanceof User && $folder->createdBy->id === $user->id;
        if ($strategy === self::STRATEGY_MOVE_TO_ROOT && !$isOwner && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul le créateur ou un administrateur peut supprimer ce dossier');
        }

        // Everything the cascade is about to archive, read before any of it is touched: both guards
        // have to be able to refuse the whole operation with nothing written.
        $filesToArchive = [];
        if ($strategy === self::STRATEGY_CASCADE) {
            $descendantIds = $this->folderRepository->findDescendantIds($folder);
            $this->assertSubtreeIsSmallEnough(array_sum($this->fileRepository->countActiveByFolderIds($descendantIds)));
            $filesToArchive = $this->fileRepository->findActiveByFolderIds($descendantIds);
        }

        $this->assertNoAttachedFile($filesToArchive);

        $this->activityRecorder->record(
            $membership->bandSpace,
            BandSpaceModule::File,
            BandSpaceFolderActivityType::FolderArchived,
            resourceId: (string) $folder->id,
            actor: $user,
            payload: ['name' => $folder->name, 'strategy' => $strategy, 'file_count' => count($filesToArchive)],
        );

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            if ($strategy === self::STRATEGY_CASCADE) {
                $this->archive($filesToArchive, $membership->bandSpace, $user);
            } else {
                $this->folderRepository->detachChildrenFrom($folder);
                $this->fileRepository->detachFromFolder($folder);
            }

            $this->entityManager->remove($folder);
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function assertSubtreeIsSmallEnough(int $fileCount): void
    {
        if ($fileCount <= self::MAX_CASCADE_FILES) {
            return;
        }

        throw new UnprocessableEntityHttpException(sprintf(
            'Ce dossier contient %d fichiers, au-delà de la limite de %d par suppression. Supprimez ses sous-dossiers un par un, ou déplacez une partie des fichiers avant de réessayer.',
            $fileCount,
            self::MAX_CASCADE_FILES,
        ));
    }

    /**
     * Refuses the cascade when it would trash a file some task, note or finance entry still points at.
     *
     * The single file endpoint has always refused exactly this, and the cascade did not: attachment
     * panels list live files only, so the file simply vanished from the resource holding it and
     * app:band-space:purge destroyed it once the grace period ran out (#823).
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
            ? sprintf("Ce dossier contient 1 fichier attaché à %s. Détachez-le d'abord depuis la ressource concernée.", $sources)
            : sprintf(
                "Ce dossier contient %d fichiers attachés à %s. Détachez-les d'abord depuis les ressources concernées.",
                count($sourceTypesByFile),
                $sources,
            ));
    }

    /**
     * Trashes the subtree file by file, the way the single file endpoint does it.
     *
     * The folder rows are about to go and band_space_file.folder_id is ON DELETE SET NULL, so each
     * file keeps the path it was archived from in its Archived activity: without it a restore drops
     * the file at the root with nothing left saying where it used to live.
     *
     * Paths are memoised per folder. findActiveByFolderIds() fetch joins the file's own folder but not
     * its ancestors, so walking the chain pulls each ancestor in once; the identity map then serves
     * every later walk, which caps the extra queries at the number of distinct folders in the subtree
     * plus the chain above it, never at the number of files. Six levels at most, MAX_DEPTH sees to it.
     *
     * @param BandSpaceFile[] $files
     */
    private function archive(array $files, BandSpace $bandSpace, User $actor): void
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
