<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Attribute;

use Zenstruck\Foundry\Factory;
use App\Entity\Attribute\Instrument;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/** @codeCoverageIgnore */
final class InstrumentFactory extends PersistentProxyObjectFactory
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

    public function asGuitar(): Factory
    {
        return $this->with([
            'musicianName' => 'Guitariste',
            'name' => 'Guitare',
            'slug' => 'guitare',
        ]);
    }

    public function asUkulele(): Factory
    {
        return $this->with([
            'musicianName' => 'Joueur / Joueuse de Ukulélé',
            'name' => 'Ukulélé',
            'slug' => 'ukulele',
        ]);
    }

    public function asFlute(): Factory
    {
        return $this->with([
            'musicianName' => 'Flûtiste',
            'name' => 'Flûte',
            'slug' => 'flute',
        ]);
    }

    public function asSaxophone(): Factory
    {
        return $this->with([
            'musicianName' => 'Saxophoniste',
            'name' => 'Saxophone',
            'slug' => 'saxophone',
        ]);
    }

    public function asTrumpet(): Factory
    {
        return $this->with([
            'musicianName' => 'Trompettiste',
            'name' => 'Trompette',
            'slug' => 'trompette',
        ]);
    }

    public function asDidgeridoo(): Factory
    {
        return $this->with([
            'musicianName' => 'Joueur / Joueuse de Didjeridoo',
            'name' => 'Didjeridoo',
            'slug' => 'didgeridoo',
        ]);
    }

    public function asDjembe(): Factory
    {
        return $this->with([
            'musicianName' => 'Joueur / Joueuse de Djembé',
            'name' => 'Djembé',
            'slug' => 'djembe',
        ]);
    }

    public function asDJ(): Factory
    {
        return $this->with([
            'musicianName' => 'DJ',
            'name' => 'DJ',
            'slug' => 'dj',
        ]);
    }

    public function asDoubleBass(): Factory
    {
        return $this->with([
            'musicianName' => 'Contre-Bassiste',
            'name' => 'Contre-Basse',
            'slug' => 'contre-basse',
        ]);
    }

    public function asViolin(): Factory
    {
        return $this->with([
            'musicianName' => 'Violoniste',
            'name' => 'Violon',
            'slug' => 'violon',
        ]);
    }

    public function asCello(): Factory
    {
        return $this->with([
            'musicianName' => 'Violoncelliste',
            'name' => 'Violoncelle',
            'slug' => 'violoncelle',
        ]);
    }

    public function asPiano(): Factory
    {
        return $this->with([
            'musicianName' => 'Pianiste',
            'name' => 'Piano',
            'slug' => 'piano',
        ]);
    }

    public function asBass(): Factory
    {
        return $this->with([
            'musicianName' => 'Bassiste',
            'name' => 'Basse',
            'slug' => 'basse',
        ]);
    }

    public function asDrums(): Factory
    {
        return $this->with([
            'musicianName' => 'Batteur / Batteuse',
            'name' => 'Batterie',
            'slug' => 'batterie',
        ]);
    }

    public function asVocals(): Factory
    {
        return $this->with([
            'musicianName' => 'Chanteur / Chanteuse',
            'name' => 'Chant',
            'slug' => 'chant',
        ]);
    }

    public function asKeyboard(): Factory
    {
        return $this->with([
            'musicianName' => 'Claviériste',
            'name' => 'Clavier',
            'slug' => 'clavier',
        ]);
    }

    public function asBanjo(): Factory
    {
        return $this->with([
            'musicianName' => 'Joueur / Joueuse de Banjo',
            'name' => 'Banjo',
            'slug' => 'banjo',
        ]);
    }

    public static function class(): string
    {
        return Instrument::class;
    }
}
