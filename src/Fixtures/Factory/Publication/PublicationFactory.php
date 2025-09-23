<?php

namespace App\Fixtures\Factory\Publication;

use App\Entity\Publication;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use function Symfony\Component\String\u;

final class PublicationFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'author' => UserFactory::new(),
            'content' => self::faker()->text(),
            'creationDatetime' => self::faker()->dateTime(),
            'editionDatetime' => self::faker()->dateTime(),
            'publicationDatetime' => self::faker()->dateTime(),
            'shortDescription' => self::faker()->text(),
            'slug' => self::faker()->text(255),
            'status' => self::faker()->numberBetween(1, 32767),
            'subCategory' => PublicationSubCategoryFactory::new(),
            'title' => self::faker()->text(255),
            'type' => self::faker()->numberBetween(1, 32767),
        ];
    }

    public function asBaseTextPublicationOnline(): Factory
    {
        $title = self::faker()->sentence();

        return $this->with([
            'content' => nl2br(self::faker()->paragraphs(random_int(5, 20), true)),
            'creationDatetime' => self::faker()->dateTime(),
            'editionDatetime' => self::faker()->dateTime(),
            'publicationDatetime' => self::faker()->dateTimeBetween('-3 years', 'now'),
            'shortDescription' => self::faker()->sentence(),
            'slug' => new AsciiSlugger()->slug($title),
            'status' => Publication::STATUS_ONLINE,
            'title' => $title,
            'type' => Publication::TYPE_TEXT,
            'cover' => PublicationCoverFactory::new()
        ]);
    }

    public static function class(): string
    {
        return Publication::class;
    }
}
