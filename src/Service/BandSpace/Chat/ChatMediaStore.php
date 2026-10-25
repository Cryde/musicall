<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileAttachment;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use App\Service\BandSpace\File\BandSpaceFilePurger;
use App\Service\BandSpace\File\BandSpaceFileQuotaService;
use App\Service\BandSpace\File\BandSpaceFileSourceTypes;
use App\Service\Message\ChatMediaPreparer;
use App\Service\Message\StoredVoiceNote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Handler\UploadHandlerInterface;

/**
 * A chat image or voice note is a band space file like any other (#973, #974): same storage, same
 * quota. What makes it chat media is its attachment row, source type `message`, which keeps it out of
 * the Files root and lists it under the « Chat » virtual folder instead.
 *
 * Everything here is stored before the message is sent, so a refused quota refuses the whole message
 * rather than leaving it without its media.
 */
readonly class ChatMediaStore
{
    public function __construct(
        private ChatMediaPreparer $mediaPreparer,
        private BandSpaceFileQuotaService $quotaService,
        private BandSpaceFilePurger $filePurger,
        private EntityManagerInterface $entityManager,
        private UploadHandlerInterface $uploadHandler,
        private BandSpaceFileVersionRepository $versionRepository,
        private BandSpaceFileRepository $fileRepository,
    ) {
    }

    public function storeImage(File $image, BandSpace $bandSpace, User $uploader): BandSpaceFile
    {
        return $this->mediaPreparer->image(
            $image,
            fn (File $content, string $mimeType, string $name): BandSpaceFile => $this->persist($content, $mimeType, $name, $bandSpace, $uploader),
        );
    }

    /**
     * @return StoredVoiceNote<BandSpaceFile>
     */
    public function storeVoiceNote(File $recording, BandSpace $bandSpace, User $uploader): StoredVoiceNote
    {
        return $this->mediaPreparer->voiceNote(
            $recording,
            fn (File $content, string $mimeType, string $name): BandSpaceFile => $this->persist($content, $mimeType, $name, $bandSpace, $uploader),
        );
    }

    /**
     * The row that makes the file chat media. Flushes, so whatever the caller has just set on the
     * message (which file it carries) is written in the same statement batch.
     */
    public function attachToMessage(BandSpaceFile $file, Message $message, User $uploader): void
    {
        $attachment = new BandSpaceFileAttachment();
        $attachment->bandSpaceFile = $file;
        $attachment->sourceType = BandSpaceFileSourceTypes::CHAT_MESSAGE;
        $attachment->sourceId = (string) $message->id;
        $attachment->attachedBy = $uploader;
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();
    }

    /**
     * For good, object included: a deleted message takes its media with it.
     */
    public function discard(BandSpaceFile $file): void
    {
        $file->archiveDatetime ??= new \DateTimeImmutable();
        $this->entityManager->flush();
        $this->filePurger->purge($file);
    }

    /**
     * discard() for a message that could not be sent: the failed send has closed the entity manager,
     * so this deletes without flushing.
     */
    public function discardUnsent(BandSpaceFile $file): void
    {
        foreach ($this->versionRepository->findByFileNewestFirst($file) as $version) {
            $this->uploadHandler->remove($version, 'uploadedFile');
            // The handler has just cleared its storage path: a later flush must not write it.
            $this->entityManager->detach($version);
        }
        $this->fileRepository->deleteWithVersionsById((string) $file->id);
        $this->entityManager->detach($file);
    }

    private function persist(File $content, string $mimeType, string $name, BandSpace $bandSpace, User $uploader): BandSpaceFile
    {
        $size = $content->getSize();
        $this->quotaService->assertCanUpload($bandSpace, $size);

        $file = new BandSpaceFile();
        $file->bandSpace = $bandSpace;
        $file->createdBy = $uploader;
        $file->originalName = $name;

        $version = new BandSpaceFileVersion();
        $version->bandSpaceFile = $file;
        $version->versionNumber = 1;
        $version->createdBy = $uploader;
        $version->mimeType = $mimeType;
        $version->size = $size;
        $version->setUploadedFile($content);

        $this->entityManager->persist($file);
        $this->entityManager->persist($version);
        $this->entityManager->flush();

        $file->currentVersion = $version;
        $this->entityManager->flush();

        return $file;
    }
}
