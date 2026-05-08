<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use App\Entity\BandSpace\BandSpace;

/**
 * Stub for the per-band file quota check. The real implementation lands in #635
 * (per-band quota + quotaBytesOverride column + usage endpoint + enforcement).
 *
 * Until then, every upload passes — quota is documented but not enforced.
 */
readonly class BandSpaceFileQuotaService
{
    public function ensureCanUpload(BandSpace $bandSpace, int $sizeBytes): void
    {
        // No-op until #635 wires the real quota.
    }
}
