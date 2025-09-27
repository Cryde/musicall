<?php

namespace App\Fixtures\Factory\Course;

use App\Entity\PublicationSubCategory;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<PublicationSubCategory>
 */
final class CourseCategoryFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PublicationSubCategory::class;
    }

    protected function defaults(): array
    {
        return [
            'position' => self::faker()->randomNumber(),
            'slug' => self::faker()->text(255),
            'title' => self::faker()->text(255),
            'type' => PublicationSubCategory::TYPE_COURSE,
        ];
    }

    public function asGuitare(): Factory
    {
        return $this->with(['title' => 'Guitare', 'slug' => 'guitare', 'position' => 1]);
    }

    public function asBasse(): Factory
    {
        return $this->with(['title' => 'Basse', 'slug' => 'basse', 'position' => 2]);
    }

    public function asBatterie(): Factory
    {
        return $this->with(['title' => 'Batterie', 'slug' => 'batterie', 'position' => 3]);
    }

    public function asMAO(): Factory
    {
        return $this->with(['title' => 'MAO', 'slug' => 'mao', 'position' => 4]);
    }

    public function asDivers(): Factory
    {
        return $this->with(['title' => 'Divers', 'slug' => 'divers', 'position' => 5]);
    }
}
