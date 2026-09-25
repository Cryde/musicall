<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileAttachment;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Service\BandSpace\File\BandSpaceFilePurger;
use App\Service\BandSpace\File\BandSpaceFileQuotaService;
use App\Service\BandSpace\File\BandSpaceFileSourceTypes;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
    private const string FALLBACK_IMAGE_BASENAME = 'image';

    public function __construct(
        private ChatImageConverter $imageConverter,
        private ChatVoiceNoteConverter $voiceNoteConverter,
        private BandSpaceFileQuotaService $quotaService,
        private BandSpaceFilePurger $filePurger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function storeImage(File $image, BandSpace $bandSpace, User $uploader): BandSpaceFile
    {
        $converted = $this->imageConverter->convert($image, $image->getMimeType() ?? '');

        try {
            return $this->persist($converted->file, $converted->mimeType, $this->imageName($image, $converted->mimeType), $bandSpace, $uploader);
        } finally {
            if ($converted->isTemporary) {
                $this->removeTemporary($converted->file);
            }
        }
    }

    public function storeVoiceNote(File $recording, BandSpace $bandSpace, User $uploader): StoredVoiceNote
    {
        $converted = $this->voiceNoteConverter->convert($recording, $recording->getMimeType() ?? '');

        try {
            $name = sprintf('note-vocale-%s.m4a', (new \DateTimeImmutable())->format('Y-m-d-His'));

            return new StoredVoiceNote(
                $this->persist($converted->file, ChatVoiceNoteConverter::OUTPUT_MIME_TYPE, $name, $bandSpace, $uploader),
                $converted->durationSeconds,
                $converted->peaks,
            );
        } finally {
            $this->removeTemporary($converted->file);
        }
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
        $attachment->sourceId = Uuid::fromString((string) $message->id);
        $attachment->attachedBy = $uploader;
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();
    }

    /**
     * For good, object included: a deleted message takes its media with it, and a message that could
     * not be sent must not leave its media behind in the Files root.
     */
    public function discard(BandSpaceFile $file): void
    {
        $file->archiveDatetime ??= new \DateTimeImmutable();
        $this->entityManager->flush();
        $this->filePurger->purge($file);
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

    private function removeTemporary(File $file): void
    {
        if (is_file($file->getPathname())) {
            unlink($file->getPathname());
        }
    }

    /** The name the sender's device gave it, with the extension of what is actually stored. */
    private function imageName(File $image, string $mimeType): string
    {
        $clientName = $image instanceof UploadedFile ? $image->getClientOriginalName() : $image->getFilename();
        $basename = pathinfo($clientName, PATHINFO_FILENAME);
        $extension = $mimeType === ChatImageConverter::OUTPUT_MIME_TYPE ? 'webp' : 'gif';

        return ($basename !== '' ? $basename : self::FALLBACK_IMAGE_BASENAME) . '.' . $extension;
    }
}
