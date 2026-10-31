<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\BandSpace;

class AdminBandSpaceStorage
{
    public string $name;
    public int $bytes = 0;
    public int $quotaBytes = 0;
}
