<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\File\BandSpaceFilePublicShareMetadata;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Service\BandSpace\ShareTokenService;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProviderInterface<BandSpaceFilePublicShareMetadata>
 */
readonly class BandSpaceFilePublicShareMetadataProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceFileShareRepository $shareRepository,
        private ShareTokenService $tokenService,
        private RequestStack $requestStack,
        #[Target('band_space_file_share_access')]
        private RateLimiterFactoryInterface $shareAccessLimiter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFilePublicShareMetadata
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

        $dto = new BandSpaceFilePublicShareMetadata();
        $dto->token = $token;
        $dto->originalName = $file->originalName;
        $dto->size = $file->currentVersion->size;
        $dto->mimeType = $file->currentVersion->mimeType;
        $dto->expiryDatetime = $share->expiryDatetime;
        $dto->hasPassword = $share->passwordHash !== null;

        return $dto;
    }
}
