<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\Chat\ChatImageConverter;
use App\Service\BandSpace\File\BandSpaceFileSourceTypes;
use App\State\Provider\BandSpace\File\BandSpaceFileDownloadProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * @implements ProviderInterface<object>
 */
readonly class ChatImageProvider implements ProviderInterface
{
    /** A day: a chat image does not change, but a member can still upload a new version from Files. */
    private const int CACHE_MAX_AGE_SECONDS = 86400;

    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private StorageInterface $vichStorage,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StreamedResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        $version = $file?->currentVersion;
        // Inline only for what the chat stored and only for an image type, so this cannot become a way
        // to render any other band space file in the page.
        if (
            !$file instanceof BandSpaceFile
            || $file->archiveDatetime instanceof \DateTimeImmutable
            || !$version instanceof BandSpaceFileVersion
            || !in_array($version->mimeType, ChatImageConverter::ACCEPTED_MIME_TYPES, true)
            || !$this->isChatImage($file)
        ) {
            throw new NotFoundHttpException('Image introuvable');
        }

        $response = BandSpaceFileDownloadProvider::stream($file, $version, $this->vichStorage, inline: true);
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE_SECONDS);

        return $response;
    }

    private function isChatImage(BandSpaceFile $file): bool
    {
        foreach ($this->attachmentRepository->findByFile($file) as $attachment) {
            if ($attachment->sourceType === BandSpaceFileSourceTypes::CHAT_MESSAGE) {
                return true;
            }
        }

        return false;
    }
}
