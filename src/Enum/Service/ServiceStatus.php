<?php

declare(strict_types=1);

namespace App\Enum\Service;

/** A draft is seen by its owner only (#1406): never in a search, the annuaire or the profile. */
enum ServiceStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
