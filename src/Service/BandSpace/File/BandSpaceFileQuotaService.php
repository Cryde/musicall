<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use App\Entity\BandSpace\BandSpace;
use App\Exception\BandSpace\QuotaExceededException;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class BandSpaceFileQuotaService
{
    public const float APPROACHING_LIMIT_RATIO = 0.80;

    public function __construct(
        private BandSpaceFileVersionRepository $versionRepository,
        #[Autowire('%env(int:BAND_SPACE_FILE_QUOTA_BYTES)%')]
        private int $defaultQuotaBytes,
    ) {
    }

    public function getQuotaBytes(BandSpace $bandSpace): int
    {
        if ($bandSpace->quotaBytesOverride !== null) {
            return $bandSpace->quotaBytesOverride;
        }

        return $this->defaultQuotaBytes;
    }

    public function getUsageBytes(BandSpace $bandSpace): int
    {
        return $this->versionRepository->sumActiveBytesByBandSpace($bandSpace);
    }

    /**
     * @return array<int, array{source: string, bytes: int}>
     */
    public function getUsageBreakdown(BandSpace $bandSpace): array
    {
        return $this->versionRepository->sumActiveBytesByBandSpaceGroupedBySource($bandSpace);
    }

    public function assertCanUpload(BandSpace $bandSpace, int $incomingBytes): void
    {
        $quota = $this->getQuotaBytes($bandSpace);
        $used = $this->getUsageBytes($bandSpace);

        if ($used + $incomingBytes > $quota) {
            throw new QuotaExceededException($quota, $used, $incomingBytes);
        }
    }

    public function isApproachingLimit(BandSpace $bandSpace): bool
    {
        $quota = $this->getQuotaBytes($bandSpace);
        if ($quota <= 0) {
            return false;
        }

        return $this->getUsageBytes($bandSpace) / $quota >= self::APPROACHING_LIMIT_RATIO;
    }
}
