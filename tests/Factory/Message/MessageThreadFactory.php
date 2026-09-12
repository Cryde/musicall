<?php

declare(strict_types=1);

namespace App\Tests\Factory\Message;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class MessageThreadFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    /** A Band Space channel: no participant rows, because its members are derived from the space. */
    public function forBandSpace(BandSpace $bandSpace, string $name = MessageThread::DEFAULT_CHANNEL_NAME): static
    {
        return $this->with(['bandSpace' => $bandSpace, 'name' => $name]);
    }

    public static function class(): string
    {
        return MessageThread::class;
    }
}
