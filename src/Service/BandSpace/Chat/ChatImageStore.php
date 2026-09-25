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
 * A chat image is a band space file like any other (#973): same storage, same quota. What makes it a
 * chat image is its attachment row, source type `message`, which keeps it out of the Files root and
 * lists it under the « Chat » virtual folder instead.
 */
readonly class ChatImageStore
{
    private const string FALLBACK_BASENAME = 'image';

    public function __construct(
        private ChatImageConverter $converter,
        private BandSpaceFileQuotaService $quotaService,
        private BandSpaceFilePurger $filePurger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Stored before the message is sent, so a refused quota refuses the whole message rather than
     * leaving it without its image.
     */
    public function store(File $image, BandSpace $bandSpace, User $uploader): BandSpaceFile
    {
        $converted = $this->converter->convert($image, $image->getMimeType() ?? '');

        try {
            $size = $converted->file->getSize();
            $this->quotaService->assertCanUpload($bandSpace, $size);

            $file = new BandSpaceFile();
            $file->bandSpace = $bandSpace;
            $file->createdBy = $uploader;
            $file->originalName = $this->storedName($image, $converted->mimeType);

            $version = new BandSpaceFileVersion();
            $version->bandSpaceFile = $file;
            $version->versionNumber = 1;
            $version->createdBy = $uploader;
            $version->mimeType = $converted->mimeType;
            $version->size = $size;
            $version->setUploadedFile($converted->file);

            $this->entityManager->persist($file);
            $this->entityManager->persist($version);
            $this->entityManager->flush();

            $file->currentVersion = $version;
            $this->entityManager->flush();
        } finally {
            if ($converted->isTemporary && is_file($converted->file->getPathname())) {
                unlink($converted->file->getPathname());
            }
        }

        return $file;
    }

    public function attachToMessage(BandSpaceFile $file, Message $message, User $uploader): void
    {
        $attachment = new BandSpaceFileAttachment();
        $attachment->bandSpaceFile = $file;
        $attachment->sourceType = BandSpaceFileSourceTypes::CHAT_MESSAGE;
        $attachment->sourceId = Uuid::fromString((string) $message->id);
        $attachment->attachedBy = $uploader;
        $this->entityManager->persist($attachment);
        $message->imageFileId = (string) $file->id;
        $this->entityManager->flush();
    }

    /**
     * For good, object included: a deleted message takes its image with it, and a message that could
     * not be sent must not leave its image behind in the Files root.
     */
    public function discard(BandSpaceFile $file): void
    {
        $file->archiveDatetime ??= new \DateTimeImmutable();
        $this->entityManager->flush();
        $this->filePurger->purge($file);
    }

    /** The name the sender's device gave it, with the extension of what is actually stored. */
    private function storedName(File $image, string $mimeType): string
    {
        $clientName = $image instanceof UploadedFile ? $image->getClientOriginalName() : $image->getFilename();
        $basename = pathinfo($clientName, PATHINFO_FILENAME);
        $extension = $mimeType === ChatImageConverter::OUTPUT_MIME_TYPE ? 'webp' : 'gif';

        return ($basename !== '' ? $basename : self::FALLBACK_BASENAME) . '.' . $extension;
    }
}
