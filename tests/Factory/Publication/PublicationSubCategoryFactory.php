<?php

declare(strict_types=1);

namespace App\Tests\Factory\Publication;

use Zenstruck\Foundry\Factory;
use App\Entity\PublicationSubCategory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PublicationSubCategory>
 */
final class PublicationSubCategoryFactory extends PersistentObjectFactory
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

    public function asNews(): \App\Tests\Factory\Publication\PublicationSubCategoryFactory
    {
        return $this->with(['title' => 'News', 'slug' => 'news', 'position' => 1]);
    }

    public function asChronique(): \App\Tests\Factory\Publication\PublicationSubCategoryFactory
    {
        return $this->with(['title' => 'Chroniques', 'slug' => 'chroniques', 'position' => 2]);
    }

    public function asInterview(): \App\Tests\Factory\Publication\PublicationSubCategoryFactory
    {
        return $this->with(['title' => 'Interviews', 'slug' => 'interviews', 'position' => 3]);
    }

    public function asLiveReports(): \App\Tests\Factory\Publication\PublicationSubCategoryFactory
    {
        return $this->with(['title' => 'Live-reports', 'slug' => 'live-reports', 'position' => 4]);
    }

    public function asArticle(): \App\Tests\Factory\Publication\PublicationSubCategoryFactory
    {
        return $this->with(['title' => 'Articles', 'slug' => 'articles', 'position' => 5]);
    }

    public function asDecouvertes(): \App\Tests\Factory\Publication\PublicationSubCategoryFactory
    {
        return $this->with(['title' => 'Découvertes', 'slug' => 'decouvertes', 'position' => 6]);
    }

    public function asCourseCategory(): \App\Tests\Factory\Publication\PublicationSubCategoryFactory
    {
        return $this->with([
            'title' => 'Guitare',
            'slug' => 'guitare',
            'position' => 1,
            'type' => PublicationSubCategory::TYPE_COURSE,
        ]);
    }

    public function asCourse(): \App\Tests\Factory\Publication\PublicationSubCategoryFactory
    {
        return $this->with([
            'title' => 'Cours',
            'slug' => 'cours',
            'position' => 1,
            'type' => PublicationSubCategory::TYPE_COURSE,
        ]);
    }

    public function asCourse2(): \App\Tests\Factory\Publication\PublicationSubCategoryFactory
    {
        return $this->with([
            'title' => 'Théorie',
            'slug' => 'theorie',
            'position' => 2,
            'type' => PublicationSubCategory::TYPE_COURSE,
        ]);
    }
}
