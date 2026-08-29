<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\File;

use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileTag;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\SetlistRepository;
use App\Repository\BandSpace\SongRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class BandSpaceFileBuilder
{
    public function __construct(
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private BandSpaceFolderRepository $folderRepository,
        private TaskRepository $taskRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private BandSpaceNoteRepository $noteRepository,
        private SongRepository $songRepository,
        private SetlistRepository $setlistRepository,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%band_space.file_retention_days%')]
        private int $retentionDays,
    ) {
    }

    /**
     * @param BandSpaceFile[] $entities all belonging to $bandSpace
     *
     * @return BandSpaceFileResource[]
     */
    public function buildFromList(array $entities, BandSpace $bandSpace): array
    {
        if (count($entities) === 0) {
            return [];
        }

        $fileIds = array_map(fn (BandSpaceFile $file): string => (string) $file->id, $entities);
        $versionCounts = $this->fileRepository->countVersionsByFileIds($fileIds);
        // One query for the whole tree, handed down the way BandSpaceFolderBuilder::buildTree() is
        // handed its counts. Without it each row walks its own ancestors, one lazy SELECT per level.
        $folderPaths = $this->folderRepository->findPathsByBandSpace($bandSpace);

        return array_map(
            fn (BandSpaceFile $entity): BandSpaceFileResource => $this->buildItem(
                $entity,
                $versionCounts[(string) $entity->id] ?? 0,
                $folderPaths,
            ),
            $entities,
        );
    }

    /**
     * @param array<string, array<int, array{id: string, name: string}>>|null $folderPaths paths of the
     *        whole space, keyed by folder id, from BandSpaceFolderRepository::findPathsByBandSpace().
     *        Null walks this file's own ancestors instead, which is what a single item wants.
     */
    public function buildItem(BandSpaceFile $entity, ?int $versionCount = null, ?array $folderPaths = null): BandSpaceFileResource
    {
        $dto = new BandSpaceFileResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->originalName = $entity->originalName;
        $dto->size = $entity->currentVersion?->size;
        $dto->mimeType = $entity->currentVersion?->mimeType;
        $dto->folderId = $entity->folder instanceof \App\Entity\BandSpace\BandSpaceFolder ? (string) $entity->folder->id : null;
        $dto->folderPath = $this->resolveFolderPath($entity->folder, $folderPaths);

        $dto->tags = array_values(array_map(
            fn (BandSpaceFileTag $tag): array => [
                'id' => (string) $tag->id,
                'name' => $tag->name,
                'color_hex' => $tag->colorHex,
            ],
            $entity->tags->toArray(),
        ));

        $dto->attachments = $this->buildAttachments($entity);

        $dto->currentVersionId = $entity->currentVersion instanceof \App\Entity\BandSpace\BandSpaceFileVersion
            ? (string) $entity->currentVersion->id
            : null;
        $dto->currentVersionNumber = $entity->currentVersion?->versionNumber;

        $dto->versionCount = $versionCount ?? $this->fileRepository->countVersionsByFileIds([(string) $entity->id])[(string) $entity->id] ?? 0;

        if ($entity->createdBy instanceof \App\Entity\User) {
            $dto->createdBy = [
                'id' => (string) $entity->createdBy->id,
                'username' => $entity->createdBy->username,
                'profile_picture_url' => $this->profilePictureUrlBuilder->build($entity->createdBy),
            ];
        }

        $dto->downloadUrl = $this->urlGenerator->generate(
            'api_band_space_files_get_item',
            [
                'bandSpaceId' => (string) $entity->bandSpace->id,
                'id' => (string) $entity->id,
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        ) . '/download';

        $dto->creationDatetime = $entity->creationDatetime;
        $dto->updateDatetime = $entity->updateDatetime;
        $dto->archiveDatetime = $entity->archiveDatetime;
        // Derived rather than stored, from the same parameter app:band-space:purge uses for its cutoff.
        $dto->purgeDatetime = $entity->archiveDatetime?->modify(sprintf('+%d days', $this->retentionDays));

        return $dto;
    }

    /**
     * The path down to $folder, read off the per space map when the caller loaded one and walked from
     * the entity when it did not. The map holds every folder of the space, so a miss means the folder
     * is not one of its own and the walk is a truer answer than an empty path.
     *
     * @param array<string, array<int, array{id: string, name: string}>>|null $folderPaths
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function resolveFolderPath(?\App\Entity\BandSpace\BandSpaceFolder $folder, ?array $folderPaths): array
    {
        if (!$folder instanceof \App\Entity\BandSpace\BandSpaceFolder) {
            return [];
        }

        return $folderPaths[(string) $folder->id] ?? $this->folderRepository->buildPath($folder);
    }

    /**
     * @return array<int, array{source_type: string, source_id: string, source_label: string}>
     */
    private function buildAttachments(BandSpaceFile $file): array
    {
        $attachments = $this->attachmentRepository->findByFile($file);
        if (count($attachments) === 0) {
            return [];
        }

        $bandSpace = $file->bandSpace;
        $taskIds = [];
        $entryIds = [];
        $noteIds = [];
        $songIds = [];
        $setlistIds = [];
        foreach ($attachments as $a) {
            match ($a->sourceType) {
                'task' => $taskIds[] = (string) $a->sourceId,
                'finance' => $entryIds[] = (string) $a->sourceId,
                'note' => $noteIds[] = (string) $a->sourceId,
                'song' => $songIds[] = (string) $a->sourceId,
                'setlist' => $setlistIds[] = (string) $a->sourceId,
                default => null,
            };
        }

        $taskTitles = [];
        if (count($taskIds) > 0) {
            foreach ($this->taskRepository->findByIdsAndBandSpace(array_values(array_unique($taskIds)), $bandSpace) as $task) {
                $taskTitles[(string) $task->id] = $task->title;
            }
        }

        $entryLabels = [];
        foreach ($entryIds as $entryId) {
            $entry = $this->financeEntryRepository->findOneByIdAndBandSpace($entryId, $bandSpace);

            // A personal entry's label is never named here, not even for its owner. This builder feeds
            // the band wide file browser and has no reader to compare against, so the choice is between
            // naming it to everybody or to nobody; the attachment still shows, only unnamed.
            if ($entry instanceof \App\Entity\BandSpace\FinanceEntry && $entry->scope !== FinanceEntryScope::Personal) {
                $entryLabels[$entryId] = $entry->label;
            }
        }

        $noteTitles = [];
        foreach ($noteIds as $noteId) {
            $note = $this->noteRepository->findOneByIdAndBandSpace($noteId, $bandSpace);
            if ($note instanceof \App\Entity\BandSpace\BandSpaceNote) {
                $noteTitles[$noteId] = $note->title;
            }
        }

        $songTitles = [];
        if (count($songIds) > 0) {
            foreach ($this->songRepository->findByIdsAndBandSpace(array_values(array_unique($songIds)), $bandSpace) as $song) {
                $songTitles[(string) $song->id] = $song->title;
            }
        }

        $setlistNames = [];
        if (count($setlistIds) > 0) {
            foreach ($this->setlistRepository->findByIdsAndBandSpace(array_values(array_unique($setlistIds)), $bandSpace) as $setlist) {
                $setlistNames[(string) $setlist->id] = $setlist->name;
            }
        }

        return array_map(static function ($a) use ($taskTitles, $entryLabels, $noteTitles, $songTitles, $setlistNames): array {
            $sourceId = (string) $a->sourceId;
            $label = match ($a->sourceType) {
                'task' => $taskTitles[$sourceId] ?? '—',
                'finance' => $entryLabels[$sourceId] ?? '—',
                'note' => $noteTitles[$sourceId] ?? '—',
                'song' => $songTitles[$sourceId] ?? '—',
                'setlist' => $setlistNames[$sourceId] ?? '—',
                default => '—',
            };

            return [
                'source_type' => $a->sourceType,
                'source_id' => $sourceId,
                'source_label' => $label,
            ];
        }, $attachments);
    }

}
