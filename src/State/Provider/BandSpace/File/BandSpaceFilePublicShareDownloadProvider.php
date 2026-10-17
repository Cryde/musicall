<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\ShareTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * @implements ProviderInterface<StreamedResponse>
 */
readonly class BandSpaceFilePublicShareDownloadProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceFileShareRepository $shareRepository,
        private ShareTokenService $tokenService,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
        private BandSpaceActivityRecorder $activityRecorder,
        private StorageInterface $vichStorage,
        private RequestStack $requestStack,
        #[Target('band_space_file_share_access')]
        private RateLimiterFactoryInterface $shareAccessLimiter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StreamedResponse
    {
        $request = $this->requestStack->getCurrentRequest();
        $clientIp = $request?->getClientIp() ?? 'unknown';
        $this->shareAccessLimiter->create($clientIp)->consume()->ensureAccepted();

        $token = (string) $uriVariables['token'];
        $share = $this->shareRepository->findOneByTokenHash($this->tokenService->hashOf($token));
        if (!$share instanceof \App\Entity\BandSpace\BandSpaceFileShare) {
            throw new NotFoundHttpException('band_space.file.share.not_found');
        }

        if ($share->revocationDatetime instanceof \DateTimeImmutable) {
            throw new GoneHttpException('band_space.file.share.revoked');
        }

        $now = new \DateTimeImmutable();
        if ($share->expiryDatetime instanceof \DateTimeImmutable && $share->expiryDatetime <= $now) {
            throw new GoneHttpException('band_space.file.share.expired');
        }

        $file = $share->bandSpaceFile;
        if ($file->archiveDatetime instanceof \DateTimeImmutable) {
            throw new NotFoundHttpException('band_space.file.not_found');
        }
        if (!$file->currentVersion instanceof \App\Entity\BandSpace\BandSpaceFileVersion) {
            throw new NotFoundHttpException('band_space.file.no_version');
        }

        if ($share->passwordHash !== null) {
            // Read the password from a header, not the query string: query strings
            // leak into access logs, browser history and the Referer header.
            $submittedPassword = $request?->headers->get('X-Share-Password') ?? '';
            if ($submittedPassword === '') {
                throw new UnauthorizedHttpException('Bearer', 'band_space.file.share.password_required');
            }
            $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
            if (!$hasher->verify($share->passwordHash, $submittedPassword)) {
                throw new UnauthorizedHttpException('Bearer', 'band_space.file.share.password_incorrect');
            }
        }

        $share->accessCount += 1;
        $share->lastAccessDatetime = $now;

        $this->activityRecorder->record(
            $file->bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::PublicAccessed,
            resourceId: (string) $file->id,
            actor: null,
            payload: [
                'share_id' => (string) $share->id,
                'ip' => $clientIp,
            ],
        );

        $this->entityManager->flush();

        return BandSpaceFileDownloadProvider::stream($file, $file->currentVersion, $this->vichStorage);
    }
}
