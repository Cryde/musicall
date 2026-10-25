<?php declare(strict_types=1);

namespace App\Service\Message;

use App\Service\BandSpace\Chat\ChatImageConverter;
use App\Service\BandSpace\Chat\ChatVoiceNoteConverter;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Turns what a member sent into what a chat stores (#973, #974, #1247): the converted file, its type
 * and its name, handed to whichever store keeps it, and the temporary copy removed whatever happens.
 */
readonly class ChatMediaPreparer
{
    private const string FALLBACK_IMAGE_BASENAME = 'image';

    public function __construct(
        private ChatImageConverter $imageConverter,
        private ChatVoiceNoteConverter $voiceNoteConverter,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(File $content, string $mimeType, string $name): T $persist
     *
     * @return T
     */
    public function image(File $image, callable $persist): mixed
    {
        $converted = $this->imageConverter->convert($image, $image->getMimeType() ?? '');

        try {
            return $persist($converted->file, $converted->mimeType, $this->imageName($image, $converted->mimeType));
        } finally {
            if ($converted->isTemporary) {
                $this->removeTemporary($converted->file);
            }
        }
    }

    /**
     * @template T of object
     *
     * @param callable(File $content, string $mimeType, string $name): T $persist
     *
     * @return StoredVoiceNote<T>
     */
    public function voiceNote(File $recording, callable $persist): StoredVoiceNote
    {
        $converted = $this->voiceNoteConverter->convert($recording, $recording->getMimeType() ?? '');

        try {
            $name = sprintf('note-vocale-%s.m4a', (new \DateTimeImmutable())->format('Y-m-d-His'));

            return new StoredVoiceNote(
                $persist($converted->file, ChatVoiceNoteConverter::OUTPUT_MIME_TYPE, $name),
                $converted->durationSeconds,
                $converted->peaks,
            );
        } finally {
            $this->removeTemporary($converted->file);
        }
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
