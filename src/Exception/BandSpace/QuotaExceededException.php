<?php declare(strict_types=1);

namespace App\Exception\BandSpace;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class QuotaExceededException extends UnprocessableEntityHttpException
{
    public function __construct(int $quotaBytes, int $usedBytes, int $incomingBytes)
    {
        parent::__construct(sprintf(
            'Quota de stockage dépassé : %d octets utilisés + %d octets envoyés > %d octets autorisés',
            $usedBytes,
            $incomingBytes,
            $quotaBytes,
        ));
    }
}
