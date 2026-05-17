<?php

declare(strict_types=1);

namespace App\Tests\Factory\Publication;

use App\Entity\Publication\Tag;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Tag>
 */
final class TagFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Tag::class;
    }

    protected function defaults(): array
    {
        $slug = self::faker()->unique()->slug(2);

        return [
            'label' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
        ];
    }
}
