<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Publication;

use App\Entity\PublicationSubCategory;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
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

    public function asNews(): static
    {
        return $this->with(['title' => 'News', 'slug' => 'news', 'position' => 1]);
    }

    public function asChronique(): static
    {
        return $this->with(['title' => 'Chroniques', 'slug' => 'chroniques', 'position' => 2]);
    }

    public function asInterview(): static
    {
        return $this->with(['title' => 'Interviews', 'slug' => 'interviews', 'position' => 3]);
    }

    public function asLiveReports(): static
    {
        return $this->with(['title' => 'Live-reports', 'slug' => 'live-reports', 'position' => 4]);
    }

    public function asArticle(): static
    {
        return $this->with(['title' => 'Articles', 'slug' => 'articles', 'position' => 5]);
    }

    public function asDecouvertes(): static
    {
        return $this->with(['title' => 'Découvertes', 'slug' => 'decouvertes', 'position' => 6]);
    }
}
