<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\File;

use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileTag;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class BandSpaceFileBuilder
{
    public function __construct(
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private TaskRepository $taskRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private BandSpaceNoteRepository $noteRepository,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param BandSpaceFile[] $entities
     *
     * @return BandSpaceFileResource[]
     */
    public function buildFromList(array $entities): array
    {
        $fileIds = array_map(fn (BandSpaceFile $file): string => (string) $file->id, $entities);
        $versionCounts = $this->fileRepository->countVersionsByFileIds($fileIds);

        return array_map(
            fn (BandSpaceFile $entity): BandSpaceFileResource => $this->buildItem(
                $entity,
                $versionCounts[(string) $entity->id] ?? 0,
            ),
            $entities,
        );
    }

    public function buildItem(BandSpaceFile $entity, ?int $versionCount = null): BandSpaceFileResource
    {
        $dto = new BandSpaceFileResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->originalName = $entity->originalName;
        $dto->size = $entity->currentVersion?->size;
        $dto->mimeType = $entity->currentVersion?->mimeType;
        $dto->folderId = $entity->folder !== null ? (string) $entity->folder->id : null;
        $dto->folderPath = $this->fileRepository->buildFolderPath($entity->folder);

        $dto->tags = array_values(array_map(
            fn (BandSpaceFileTag $tag): array => [
                'id' => (string) $tag->id,
                'name' => $tag->name,
                'color_hex' => $tag->colorHex,
            ],
            $entity->tags->toArray(),
        ));

        $dto->attachments = $this->buildAttachments($entity);

        $dto->currentVersionId = $entity->currentVersion !== null
            ? (string) $entity->currentVersion->id
            : null;
        $dto->currentVersionNumber = $entity->currentVersion?->versionNumber;

        $dto->versionCount = $versionCount ?? $this->fileRepository->countVersionsByFileIds([(string) $entity->id])[(string) $entity->id] ?? 0;

        if ($entity->createdBy !== null) {
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

        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(\DateTimeInterface::ATOM);

        return $dto;
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
        foreach ($attachments as $a) {
            match ($a->sourceType) {
                'task' => $taskIds[] = (string) $a->sourceId,
                'finance' => $entryIds[] = (string) $a->sourceId,
                'note' => $noteIds[] = (string) $a->sourceId,
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
            if ($entry !== null) {
                $entryLabels[$entryId] = $entry->label;
            }
        }

        $noteTitles = [];
        foreach ($noteIds as $noteId) {
            $note = $this->noteRepository->findOneByIdAndBandSpace($noteId, $bandSpace);
            if ($note !== null) {
                $noteTitles[$noteId] = $note->title;
            }
        }

        return array_map(static function ($a) use ($taskTitles, $entryLabels, $noteTitles): array {
            $sourceId = (string) $a->sourceId;
            $label = match ($a->sourceType) {
                'task' => $taskTitles[$sourceId] ?? '—',
                'finance' => $entryLabels[$sourceId] ?? '—',
                'note' => $noteTitles[$sourceId] ?? '—',
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
