<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\File\BandSpaceFileSourceTypes;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The gates in front of a chat image or voice note stream (#973, #974): a member of the space, a live
 * file of that space, stored by the chat itself, and of the expected type. Only then does a file
 * become something the page may show inline.
 */
readonly class ChatMediaResolver
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
    ) {
    }

    /**
     * @param list<string> $acceptedMimeTypes
     *
     * @return array{BandSpaceFile, BandSpaceFileVersion}
     */
    public function resolve(string $bandSpaceId, string $fileId, User $user, array $acceptedMimeTypes, string $notFoundMessage): array
    {
        [$bandSpace] = $this->memberChecker->checkMember($bandSpaceId, $user);

        $file = $this->fileRepository->findOneByIdAndBandSpace($fileId, $bandSpace);
        $version = $file?->currentVersion;
        if (
            !$file instanceof BandSpaceFile
            || $file->archiveDatetime instanceof \DateTimeImmutable
            || !$version instanceof BandSpaceFileVersion
            || !in_array($version->mimeType, $acceptedMimeTypes, true)
            || !$this->isChatMedia($file)
        ) {
            throw new NotFoundHttpException($notFoundMessage);
        }

        return [$file, $version];
    }

    private function isChatMedia(BandSpaceFile $file): bool
    {
        foreach ($this->attachmentRepository->findByFile($file) as $attachment) {
            if ($attachment->sourceType === BandSpaceFileSourceTypes::CHAT_MESSAGE) {
                return true;
            }
        }

        return false;
    }
}
