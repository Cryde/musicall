<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileTag;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileTagRepository;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class BandSpaceFileUpdateProcedure
{
    private const int ORIGINAL_NAME_MAX_LENGTH = 255;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceFolderRepository $folderRepository,
        private BandSpaceFileTagRepository $tagRepository,
        private BandSpaceActivityRecorder $activityRecorder,
    ) {
    }

    /**
     * @param array<string, mixed> $payload  raw merge-patch payload used to detect explicitly-sent fields
     */
    public function update(
        BandSpaceFile $file,
        array $payload,
        BandSpaceFileResource $data,
        BandSpace $bandSpace,
        User $user,
    ): BandSpaceFile {
        $changed = false;

        if (array_key_exists('original_name', $payload) || array_key_exists('originalName', $payload)) {
            if ($this->applyRename($file, $data->originalName, $user)) {
                $changed = true;
            }
        }

        if (array_key_exists('folder_id', $payload) || array_key_exists('folderId', $payload)) {
            if ($this->applyMove($file, $data->folderId, $bandSpace, $user)) {
                $changed = true;
            }
        }

        if (array_key_exists('tag_ids', $payload) || array_key_exists('tagIds', $payload)) {
            $rawTagIds = $payload['tag_ids'] ?? $payload['tagIds'] ?? [];
            if ($this->applyTags($file, $rawTagIds, $bandSpace, $user)) {
                $changed = true;
            }
        }

        $this->guardForbiddenAttach($payload);

        if (array_key_exists('attached_source_type', $payload) || array_key_exists('attachedSourceType', $payload)
            || array_key_exists('attached_source_id', $payload) || array_key_exists('attachedSourceId', $payload)
        ) {
            if ($this->applyDetach($file, $user)) {
                $changed = true;
            }
        }

        if ($changed) {
            $file->updateDatetime = new DateTime();
        }

        $this->entityManager->flush();

        return $file;
    }

    private function applyRename(BandSpaceFile $file, ?string $newName, User $user): bool
    {
        if ($newName === null) {
            throw new UnprocessableEntityHttpException('Le nom du fichier ne peut pas être vide');
        }

        $sanitised = $this->sanitiseFileName($newName);
        if ($sanitised === '') {
            throw new UnprocessableEntityHttpException('Le nom du fichier ne peut pas être vide');
        }
        if (mb_strlen($sanitised) > self::ORIGINAL_NAME_MAX_LENGTH) {
            throw new UnprocessableEntityHttpException(sprintf('Le nom du fichier ne peut pas dépasser %d caractères', self::ORIGINAL_NAME_MAX_LENGTH));
        }

        if ($sanitised === $file->originalName) {
            return false;
        }

        $oldName = $file->originalName;
        $file->originalName = $sanitised;

        $this->activityRecorder->record(
            bandSpace: $file->bandSpace,
            module: BandSpaceModule::File,
            type: BandSpaceFileActivityType::Renamed,
            resourceId: (string) $file->id,
            actor: $user,
            payload: ['from' => $oldName, 'to' => $sanitised],
        );

        return true;
    }

    private function applyMove(BandSpaceFile $file, ?string $folderId, BandSpace $bandSpace, User $user): bool
    {
        $newFolder = null;
        if ($folderId !== null && $folderId !== '') {
            $newFolder = $this->folderRepository->findOneByIdAndBandSpace($folderId, $bandSpace);
            if ($newFolder === null) {
                throw new UnprocessableEntityHttpException('Dossier introuvable dans ce Band Space');
            }
        }

        $oldFolderId = $file->folder !== null ? (string) $file->folder->id : null;
        $newFolderId = $newFolder !== null ? (string) $newFolder->id : null;
        if ($oldFolderId === $newFolderId) {
            return false;
        }

        $file->folder = $newFolder;

        $this->activityRecorder->record(
            bandSpace: $file->bandSpace,
            module: BandSpaceModule::File,
            type: BandSpaceFileActivityType::Moved,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'from_folder_id' => $oldFolderId,
                'to_folder_id' => $newFolderId,
                'to_folder_name' => $newFolder?->name,
            ],
        );

        return true;
    }

    /**
     * @param mixed $rawTagIds
     */
    private function applyTags(BandSpaceFile $file, $rawTagIds, BandSpace $bandSpace, User $user): bool
    {
        if (!is_array($rawTagIds)) {
            throw new BadRequestHttpException('tag_ids doit être un tableau');
        }

        $tagIds = array_values(array_unique(array_map('strval', $rawTagIds)));

        $newTags = [];
        if (count($tagIds) > 0) {
            $found = $this->tagRepository->findBy(['id' => $tagIds, 'bandSpace' => $bandSpace]);
            if (count($found) !== count($tagIds)) {
                throw new UnprocessableEntityHttpException('Une ou plusieurs étiquettes sont invalides pour ce Band Space');
            }
            $newTags = $found;
        }

        /** @var array<string, BandSpaceFileTag> $newById */
        $newById = [];
        foreach ($newTags as $tag) {
            $newById[(string) $tag->id] = $tag;
        }

        /** @var array<string, BandSpaceFileTag> $currentById */
        $currentById = [];
        foreach ($file->tags as $tag) {
            $currentById[(string) $tag->id] = $tag;
        }

        $added = array_diff_key($newById, $currentById);
        $removed = array_diff_key($currentById, $newById);

        if (count($added) === 0 && count($removed) === 0) {
            return false;
        }

        foreach ($removed as $tag) {
            $file->tags->removeElement($tag);
            $this->activityRecorder->record(
                bandSpace: $file->bandSpace,
                module: BandSpaceModule::File,
                type: BandSpaceFileActivityType::Untagged,
                resourceId: (string) $file->id,
                actor: $user,
                payload: ['tag_id' => (string) $tag->id, 'tag_name' => $tag->name],
            );
        }

        foreach ($added as $tag) {
            $file->tags->add($tag);
            $this->activityRecorder->record(
                bandSpace: $file->bandSpace,
                module: BandSpaceModule::File,
                type: BandSpaceFileActivityType::Tagged,
                resourceId: (string) $file->id,
                actor: $user,
                payload: ['tag_id' => (string) $tag->id, 'tag_name' => $tag->name],
            );
        }

        return true;
    }

    private function applyDetach(BandSpaceFile $file, User $user): bool
    {
        if ($file->attachedSourceType === null && $file->attachedSourceId === null) {
            return false;
        }

        $payload = [
            'from_source_type' => $file->attachedSourceType,
            'from_source_id' => $file->attachedSourceId !== null ? (string) $file->attachedSourceId : null,
        ];

        $file->attachedSourceType = null;
        $file->attachedSourceId = null;

        $this->activityRecorder->record(
            bandSpace: $file->bandSpace,
            module: BandSpaceModule::File,
            type: BandSpaceFileActivityType::Detached,
            resourceId: (string) $file->id,
            actor: $user,
            payload: $payload,
        );

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function guardForbiddenAttach(array $payload): void
    {
        $type = $payload['attached_source_type'] ?? $payload['attachedSourceType'] ?? null;
        $id = $payload['attached_source_id'] ?? $payload['attachedSourceId'] ?? null;

        if ($type !== null || $id !== null) {
            throw new UnprocessableEntityHttpException(
                "L'attachement à une ressource doit être effectué via les endpoints dédiés (tâche ou finance)",
            );
        }
    }

    private function sanitiseFileName(string $name): string
    {
        $stripped = str_replace(['/', '\\'], '', $name);

        return trim($stripped);
    }
}
