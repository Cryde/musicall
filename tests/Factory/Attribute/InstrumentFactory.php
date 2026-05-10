<?php

declare(strict_types=1);

namespace App\Tests\Factory\Attribute;

use Zenstruck\Foundry\Factory;
use App\Entity\Attribute\Instrument;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class InstrumentFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'musicianName' => self::faker()->text(255),
            'name' => self::faker()->text(255),
            'slug' => self::faker()->text(255),
        ];
    }

    public function asDrum(): \App\Tests\Factory\Attribute\InstrumentFactory
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'musicianName' => 'Batteur',
            'name' => 'Batterie',
            'slug' => 'batterie',
        ]);
    }

    public function asGuitar(): \App\Tests\Factory\Attribute\InstrumentFactory
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'musicianName' => 'Guitariste',
            'name' => 'Guitare',
            'slug' => 'guitare',
        ]);
    }

    public function asPiano(): \App\Tests\Factory\Attribute\InstrumentFactory
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'musicianName' => 'Pianiste',
            'name' => 'Piano',
            'slug' => 'piano',
        ]);
    }

    public static function class(): string
    {
        return Instrument::class;
    }
}
