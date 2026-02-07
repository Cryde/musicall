<?php declare(strict_types=1);

namespace App\Service\Builder\Metric;

use App\Entity\Metric\VoteCache;

class VoteCacheDirector
{
    public function build(): VoteCache
    {
        return new VoteCache();
    }
}
