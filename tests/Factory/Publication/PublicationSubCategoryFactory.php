<?php

namespace App\Tests\Factory\Publication;

use Zenstruck\Foundry\Factory;
use App\Entity\PublicationSubCategory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PublicationSubCategory>
 */
final class PublicationSubCategoryFactory extends PersistentProxyObjectFactory
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
            'type' => PublicationSubCategory::TYPE_PUBLICATION,
        ];
    }

    public function asNews(): Factory
    {
        return $this->with(['title' => 'News', 'slug' => 'news', 'position' => 1]);
    }

    public function asChronique(): Factory
    {
        return $this->with(['title' => 'Chroniques', 'slug' => 'chroniques', 'position' => 2]);
    }

    public function asInterview(): Factory
    {
        return $this->with(['title' => 'Interviews', 'slug' => 'interviews', 'position' => 3]);
    }

    public function asLiveReports(): Factory
    {
        return $this->with(['title' => 'Live-reports', 'slug' => 'live-reports', 'position' => 4]);
    }

    public function asArticle(): Factory
    {
        return $this->with(['title' => 'Articles', 'slug' => 'articles', 'position' => 5]);
    }

    public function asDecouvertes(): Factory
    {
        return $this->with(['title' => 'Découvertes', 'slug' => 'decouvertes', 'position' => 6]);
    }

    public function asCourseCategory(): Factory
    {
        return $this->with([
            'title' => 'Guitare',
            'slug' => 'guitare',
            'position' => 1,
            'type' => PublicationSubCategory::TYPE_COURSE,
        ]);
    }

    public function asCourse(): Factory
    {
        return $this->with([
            'title' => 'Cours',
            'slug' => 'cours',
            'position' => 1,
            'type' => PublicationSubCategory::TYPE_COURSE,
        ]);
    }

    public function asCourse2(): Factory
    {
        return $this->with([
            'title' => 'Théorie',
            'slug' => 'theorie',
            'position' => 2,
            'type' => PublicationSubCategory::TYPE_COURSE,
        ]);
    }
}
