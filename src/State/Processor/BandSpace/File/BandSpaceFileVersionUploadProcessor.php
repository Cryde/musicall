<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileVersionResource;
use App\ApiResource\BandSpace\File\BandSpaceFileVersionUpload;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\EventListener\BandSpaceFileQuotaApproachingHeaderListener;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileMimeAllowlist;
use App\Service\BandSpace\File\BandSpaceFileQuotaService;
use App\Service\Builder\BandSpace\File\BandSpaceFileVersionBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<BandSpaceFileVersionUpload, BandSpaceFileVersionResource>
 */
readonly class BandSpaceFileVersionUploadProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileVersionRepository $versionRepository,
        private BandSpaceFileQuotaService $quotaService,
        private BandSpaceActivityRecorder $activityRecorder,
        private BandSpaceFileVersionBuilder $versionBuilder,
        private Security $security,
        private RequestStack $requestStack,
        #[Target('band_space_file_upload')]
        private RateLimiterFactoryInterface $uploadLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileVersionResource
    {
        /** @var BandSpaceFileVersionUpload $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $this->uploadLimiter->create($user->id)->consume()->ensureAccepted();

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['fileId'], $bandSpace);
        if (!$file instanceof \App\Entity\BandSpace\BandSpaceFile || $file->archiveDatetime instanceof \DateTimeImmutable) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        $upload = $data->uploadedFile;
        if ($upload === null) {
            throw new BadRequestHttpException('Aucun fichier fourni');
        }

        $mimeType = $upload->getMimeType() ?? 'application/octet-stream';
        if (!BandSpaceFileMimeAllowlist::isAllowed($mimeType)) {
            throw new UnsupportedMediaTypeHttpException(sprintf('Type de fichier non autorisé : %s', $mimeType));
        }

        $size = $upload->getSize();
        if ($size === false) {
            throw new BadRequestHttpException('Taille de fichier invalide');
        }

        $this->quotaService->assertCanUpload($bandSpace, $size);

        $nextVersionNumber = $this->versionRepository->findMaxVersionNumber($file) + 1;

        $version = new BandSpaceFileVersion();
        $version->bandSpaceFile = $file;
        $version->versionNumber = $nextVersionNumber;
        $version->createdBy = $user;
        $version->mimeType = $mimeType;
        $version->size = $size;
        $version->setUploadedFile($upload);

        $file->currentVersion = $version;
        $file->updateDatetime = new \DateTime();

        $this->entityManager->persist($version);

        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::VersionAdded,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'version_number' => $nextVersionNumber,
                'size' => $size,
                'mime_type' => $mimeType,
            ],
        );

        $this->entityManager->flush();

        if ($this->quotaService->isApproachingLimit($bandSpace)) {
            $this->requestStack->getCurrentRequest()?->attributes->set(
                BandSpaceFileQuotaApproachingHeaderListener::REQUEST_ATTRIBUTE,
                true,
            );
        }

        return $this->versionBuilder->buildItem($version, $nextVersionNumber);
    }
}
