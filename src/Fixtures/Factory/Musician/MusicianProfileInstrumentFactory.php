<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Musician;

use App\Entity\Musician\MusicianProfileInstrument;
use App\Enum\Musician\SkillLevel;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
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

    public function beginner(): self
    {
        return $this->with([
            'skillLevel' => SkillLevel::BEGINNER,
        ]);
    }

    public function intermediate(): self
    {
        return $this->with([
            'skillLevel' => SkillLevel::INTERMEDIATE,
        ]);
    }

    public function advanced(): self
    {
        return $this->with([
            'skillLevel' => SkillLevel::ADVANCED,
        ]);
    }

    public function professional(): self
    {
        return $this->with([
            'skillLevel' => SkillLevel::PROFESSIONAL,
        ]);
    }

    public static function class(): string
    {
        return MusicianProfileInstrument::class;
    }
}
