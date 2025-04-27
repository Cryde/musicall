<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Publication;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

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

    public static function class(): string
    {
        return Publication::class;
    }
}
