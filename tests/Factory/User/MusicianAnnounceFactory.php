<?php

namespace App\Tests\Factory\User;

use App\Entity\Musician\MusicianAnnounce;
use App\Tests\Factory\Attribute\InstrumentFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class MusicianAnnounceFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'author' => UserFactory::new(),
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'instrument' => InstrumentFactory::new(),
            'latitude' => self::faker()->text(255),
            'locationName' => self::faker()->text(255),
            'longitude' => self::faker()->text(255),
            'note' => self::faker()->text(),
            'type' => self::faker()->numberBetween(1, 32767),
        ];
    }

    public function withInstrument($instrument)
    {
        return $this->with(['instrument' => $instrument]);
    }

    public function withStyles(iterable $styles)
    {
        return $this->with([
            'styles' => $styles,
        ]);
    }

    public function asBand()
    {
        return $this->with(['type' => MusicianAnnounce::TYPE_BAND]);
    }

    public function asMusician()
    {
        return $this->with(['type' => MusicianAnnounce::TYPE_MUSICIAN]);
    }

    public static function class(): string
    {
        return MusicianAnnounce::class;
    }
}
