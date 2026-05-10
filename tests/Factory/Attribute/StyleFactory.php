<?php

declare(strict_types=1);

namespace App\Tests\Factory\Attribute;

use Zenstruck\Foundry\Factory;
use App\Entity\Attribute\Style;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class StyleFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'name' => self::faker()->text(255),
            'slug' => self::faker()->text(255),
        ];
    }

    public function asRock(): \App\Tests\Factory\Attribute\StyleFactory
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'name' => 'Rock',
            'slug' => 'rock',
        ]);
    }

    public function asPop(): \App\Tests\Factory\Attribute\StyleFactory
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'name' => 'Pop',
            'slug' => 'pop',
        ]);
    }

    public function asJazz(): \App\Tests\Factory\Attribute\StyleFactory
    {
        return $this->with([
            'name' => 'Jazz',
            'slug' => 'jazz',
        ]);
    }

    public function asMetal(): \App\Tests\Factory\Attribute\StyleFactory
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'name' => 'Metal',
            'slug' => 'metal',
        ]);
    }

    public static function class(): string
    {
        return Style::class;
    }
}
