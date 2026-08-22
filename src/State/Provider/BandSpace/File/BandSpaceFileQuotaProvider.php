<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\ApiResource\BandSpace\File\BandSpaceFileQuotaResource;
use App\Entity\User;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\File\BandSpaceFileMimeAllowlist;
use App\Service\BandSpace\File\BandSpaceFileQuotaService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<BandSpaceFileQuotaResource>
 */
readonly class BandSpaceFileQuotaProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileQuotaService $quotaService,
        private Security $security,
        #[Autowire('%band_space.file_retention_days%')]
        private int $retentionDays,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileQuotaResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $quotaBytes = $this->quotaService->getQuotaBytes($bandSpace);
        $usedBytes = $this->quotaService->getUsageBytes($bandSpace);

        $dto = new BandSpaceFileQuotaResource();
        $dto->bandSpaceId = (string) $bandSpace->id;
        $dto->quotaBytes = $quotaBytes;
        $dto->usedBytes = $usedBytes;
        $dto->usedPercentage = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0.0;
        $dto->isApproachingLimit = $quotaBytes > 0
            && $usedBytes / $quotaBytes >= BandSpaceFileQuotaService::APPROACHING_LIMIT_RATIO;
        $dto->breakdownBySource = $this->quotaService->getUsageBreakdown($bandSpace);
        $dto->trashRetentionDays = $this->retentionDays;
        $dto->maxUploadSizeBytes = BandSpaceFileMimeAllowlist::MAX_UPLOAD_SIZE_BYTES;

        return $dto;
    }
}
