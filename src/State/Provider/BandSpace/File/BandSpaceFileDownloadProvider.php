<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Entity\User;
use App\Http\ContentDisposition;
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
            throw new NotFoundHttpException('Fichier introuvable');
        }
        if (!$file->currentVersion instanceof \App\Entity\BandSpace\BandSpaceFileVersion) {
            throw new NotFoundHttpException('Aucune version disponible pour ce fichier');
        }

        return self::stream($file, $file->currentVersion, $this->vichStorage);
    }

    public static function stream(BandSpaceFile $file, BandSpaceFileVersion $version, StorageInterface $vichStorage): StreamedResponse
    {
        $stream = $vichStorage->resolveStream($version, 'uploadedFile');
        if ($stream === null) {
            throw new NotFoundHttpException('Le binaire de cette version est introuvable');
        }

        $response = new StreamedResponse(function () use ($stream): void {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', $version->mimeType);
        // Stop browsers from MIME-sniffing the body into an executable type
        // (defence-in-depth alongside the attachment disposition below).
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // ContentDisposition sanitises the (user-supplied) original filename so a
        // non-ASCII or "/"-containing name cannot 500 the download. Shared by the
        // authenticated, versioned and public-share paths via ::stream().
        $response->headers->set(
            'Content-Disposition',
            ContentDisposition::attachment($file->originalName),
        );
        if ($version->size !== null) {
            $response->headers->set('Content-Length', (string) $version->size);
        }

        return $response;
    }
}
