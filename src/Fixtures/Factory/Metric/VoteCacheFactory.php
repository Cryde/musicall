<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Metric;

use App\Entity\Metric\VoteCache;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<VoteCache>
 */
final class VoteCacheFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'upvoteCount' => 0,
            'downvoteCount' => 0,
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return VoteCache::class;
    }
}
