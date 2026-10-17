<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Exception\TranslatableHttpException;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * @implements ProviderInterface<StreamedResponse>
 */
readonly class BandSpaceFileVersionDownloadProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileVersionRepository $versionRepository,
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

        $versionNumber = (int) $uriVariables['versionNumber'];
        $version = $this->versionRepository->findOneByFileAndVersionNumber($file, $versionNumber);
        if (!$version instanceof \App\Entity\BandSpace\BandSpaceFileVersion) {
            throw new TranslatableHttpException(
                Response::HTTP_NOT_FOUND,
                'band_space.file.version_not_found',
                ['version' => (string) $versionNumber],
            );
        }

        return BandSpaceFileDownloadProvider::stream($file, $version, $this->vichStorage);
    }
}
