<?php

declare(strict_types=1);

namespace App\Tests\Factory\Musician;

use App\Entity\Musician\MusicianProfile;
use App\Enum\Musician\AvailabilityStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<MusicianProfile>
 */
final class MusicianProfileFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    public function withAvailabilityStatus(AvailabilityStatus $status): static
    {
        return $this->with(['availabilityStatus' => $status]);
    }

    public static function class(): string
    {
        return MusicianProfile::class;
    }
}
