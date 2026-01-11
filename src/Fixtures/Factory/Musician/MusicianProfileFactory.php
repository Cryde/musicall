<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Musician;

use App\Entity\Musician\MusicianProfile;
use App\Enum\Musician\AvailabilityStatus;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<MusicianProfile>
 */
final class MusicianProfileFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'availabilityStatus' => self::faker()->optional(0.7)->randomElement(AvailabilityStatus::cases()),
        ];
    }

    public function lookingForBand(): self
    {
        return $this->with([
            'availabilityStatus' => AvailabilityStatus::LOOKING_FOR_BAND,
        ]);
    }

    public function availableForSessions(): self
    {
        return $this->with([
            'availabilityStatus' => AvailabilityStatus::AVAILABLE_FOR_SESSIONS,
        ]);
    }

    public function openToCollaborations(): self
    {
        return $this->with([
            'availabilityStatus' => AvailabilityStatus::OPEN_TO_COLLABORATIONS,
        ]);
    }

    public function notAvailable(): self
    {
        return $this->with([
            'availabilityStatus' => AvailabilityStatus::NOT_AVAILABLE,
        ]);
    }

    public static function class(): string
    {
        return MusicianProfile::class;
    }
}
