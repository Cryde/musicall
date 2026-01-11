<?php

declare(strict_types=1);

namespace App\Tests\Factory\Musician;

use App\Entity\Musician\MusicianProfileInstrument;
use App\Enum\Musician\SkillLevel;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<MusicianProfileInstrument>
 */
final class MusicianProfileInstrumentFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'skillLevel' => self::faker()->randomElement(SkillLevel::cases()),
        ];
    }

    public static function class(): string
    {
        return MusicianProfileInstrument::class;
    }
}
