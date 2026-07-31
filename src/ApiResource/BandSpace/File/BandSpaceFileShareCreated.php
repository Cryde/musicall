<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

class BandSpaceFileShareCreated
{
    public string $shareId;
    public string $shareUrl;
    public \DateTimeInterface $expiryDatetime;
    public bool $hasPassword = false;
}
