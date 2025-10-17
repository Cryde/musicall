<?php declare(strict_types=1);

namespace App\Service\Builder\Metric;

use App\Entity\Metric\ViewCache;

class ViewCacheDirector
{
    public function build(): ViewCache
    {
        return new ViewCache();
    }
}
