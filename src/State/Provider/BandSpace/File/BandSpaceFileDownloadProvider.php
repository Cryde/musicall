<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Entity\User;
use App\Http\StoredFileResponse;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * @implements ProviderInterface<StreamedResponse>
 */
readonly class BandSpaceFileDownloadProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileRepository $fileRepository,
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
        if (!$file instanceof \App\Entity\BandSpace\BandSpaceFile || $file->archiveDatetime instanceof \DateTimeImmutable) {
            throw new NotFoundHttpException('band_space.file.not_found');
        }
        if (!$file->currentVersion instanceof \App\Entity\BandSpace\BandSpaceFileVersion) {
            throw new NotFoundHttpException('band_space.file.no_version');
        }

        return self::stream($file, $file->currentVersion, $this->vichStorage);
    }

    /**
     * @param bool $inline only for a chat image, which the page shows in place (#973)
     */
    public static function stream(BandSpaceFile $file, BandSpaceFileVersion $version, StorageInterface $vichStorage, bool $inline = false): StreamedResponse
    {
        return StoredFileResponse::stream($vichStorage, $version, 'uploadedFile', $version->mimeType, $file->originalName, $version->size, $inline);
    }
}
