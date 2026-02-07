<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Metric;

use App\Entity\Metric\VoteCache;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<VoteCache>
 */
final class VoteCacheFactory extends PersistentProxyObjectFactory
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
