<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\File\BandSpaceFilePublicShareMetadata;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Service\BandSpace\File\FileShareTokenService;
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
        private FileShareTokenService $tokenService,
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
            throw new NotFoundHttpException('Lien de partage introuvable');
        }

        if ($share->revocationDatetime instanceof \DateTimeImmutable) {
            throw new GoneHttpException('Ce lien de partage a été révoqué');
        }

        $now = new \DateTimeImmutable();
        if ($share->expiryDatetime instanceof \DateTimeImmutable && $share->expiryDatetime <= $now) {
            throw new GoneHttpException('Ce lien de partage a expiré');
        }

        $file = $share->bandSpaceFile;
        if ($file->archiveDatetime instanceof \DateTimeImmutable) {
            throw new NotFoundHttpException('Fichier introuvable');
        }
        if (!$file->currentVersion instanceof \App\Entity\BandSpace\BandSpaceFileVersion) {
            throw new NotFoundHttpException('Aucune version disponible pour ce fichier');
        }

        $dto = new BandSpaceFilePublicShareMetadata();
        $dto->token = $token;
        $dto->originalName = $file->originalName;
        $dto->size = $file->currentVersion->size;
        $dto->mimeType = $file->currentVersion->mimeType;
        $dto->expiryDatetime = $share->expiryDatetime?->format(\DateTimeInterface::ATOM) ?? '';
        $dto->hasPassword = $share->passwordHash !== null;

        return $dto;
    }
}
