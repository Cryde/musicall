<?php declare(strict_types=1);

namespace App\Fixtures\Factory\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<BandSpace>
 */
final class BandSpaceFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->words(3, true),
            'creationDatetime' => self::faker()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    /** Every space has its chat channel from the start, as BandSpaceCreateProcessor does. */
    protected function initialize(): static
    {
        return $this->afterPersist(function (BandSpace $bandSpace): void {
            persist(MessageThread::class, [
                'bandSpace' => $bandSpace,
                'name' => MessageThread::DEFAULT_CHANNEL_NAME,
                'creationDatetime' => clone $bandSpace->creationDatetime,
            ]);
        });
    }

    public static function class(): string
    {
        return BandSpace::class;
    }
}
