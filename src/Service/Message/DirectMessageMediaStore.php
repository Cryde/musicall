<?php declare(strict_types=1);

namespace App\Service\Message;

use App\Entity\Message\MessageFile;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Handler\UploadHandlerInterface;

/**
 * Where a direct conversation keeps its images and voice notes (#1247). Stored before the message is
 * sent, as in the band chat, so a message that cannot be sent discards its media rather than
 * leaving it behind.
 */
readonly class DirectMessageMediaStore
{
    public function __construct(
        private ChatMediaPreparer $mediaPreparer,
        private EntityManagerInterface $entityManager,
        private MessageFileRepository $messageFileRepository,
        private UploadHandlerInterface $uploadHandler,
    ) {
    }

    public function storeImage(File $image, MessageThread $thread, User $uploader): MessageFile
    {
        return $this->mediaPreparer->image(
            $image,
            fn (File $content, string $mimeType, string $name): MessageFile => $this->persist($content, $mimeType, $name, $thread, $uploader),
        );
    }

    /**
     * @return StoredVoiceNote<MessageFile>
     */
    public function storeVoiceNote(File $recording, MessageThread $thread, User $uploader): StoredVoiceNote
    {
        return $this->mediaPreparer->voiceNote(
            $recording,
            fn (File $content, string $mimeType, string $name): MessageFile => $this->persist($content, $mimeType, $name, $thread, $uploader),
        );
    }

    /**
     * For good, object first, then the row. Works on a closed entity manager too, which is what a send
     * that failed inside its transaction leaves behind.
     */
    public function discard(MessageFile $file): void
    {
        $this->uploadHandler->remove($file, 'uploadedFile');
        $this->messageFileRepository->deleteById((string) $file->id);
        // Out of the identity map: the handler has just cleared its file name, and a later flush in
        // this request would otherwise try to write that to a row that is gone.
        $this->entityManager->detach($file);
    }

    private function persist(File $content, string $mimeType, string $name, MessageThread $thread, User $uploader): MessageFile
    {
        $file = new MessageFile();
        $file->thread = $thread;
        $file->uploadedBy = $uploader;
        $file->mimeType = $mimeType;
        $file->size = $content->getSize();
        $file->originalName = $name;
        $file->uploadedFile = $content;

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        return $file;
    }
}
