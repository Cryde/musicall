<?php

namespace App\Tests\Factory\Publication;

use App\Entity\PublicationSubCategory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class PublicationSubCategoryFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'position' => self::faker()->randomNumber(),
            'slug' => self::faker()->text(255),
            'title' => self::faker()->text(255),
            'type' => PublicationSubCategory::TYPE_PUBLICATION,
        ];
    }

    public function asNews()
    {
        return $this->with(['title' => 'News', 'slug' => 'news', 'position' => 1]);
    }

    public function asChronique()
    {
        return $this->with(['title' => 'Chroniques', 'slug' => 'chroniques', 'position' => 2]);
    }

    public function asInterview()
    {
        return $this->with(['title' => 'Interviews', 'slug' => 'interviews', 'position' => 3]);
    }

    public function asLiveReports()
    {
        return $this->with(['title' => 'Live-reports', 'slug' => 'live-reports', 'position' => 4]);
    }

    public function asArticle()
    {
        return $this->with(['title' => 'Articles', 'slug' => 'articles', 'position' => 5]);
    }

    public function asDecouvertes()
    {
        return $this->with(['title' => 'Découvertes', 'slug' => 'decouvertes', 'position' => 6]);
    }

    public static function class(): string
    {
        return PublicationSubCategory::class;
    }
}
