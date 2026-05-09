<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Metric;

use App\Entity\Metric\Vote;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<Vote>
 */
final class VoteFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'voteCache' => VoteCacheFactory::new(),
            'identifier' => self::faker()->sha256(),
            'value' => self::faker()->randomElement([1, -1]),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return Vote::class;
    }
}
