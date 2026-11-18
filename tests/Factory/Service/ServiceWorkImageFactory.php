<?php

declare(strict_types=1);

namespace App\Tests\Factory\Service;

use App\Entity\Service\ServiceWorkImage;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * A stored image's row, without its file: write one to the filesystem when the test streams it.
 *
 * @extends PersistentObjectFactory<ServiceWorkImage>
 */
final class ServiceWorkImageFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'work' => ServiceWorkFactory::new(),
            'position' => 0,
            'mimeType' => 'image/webp',
            'size' => 10,
            'originalName' => 'cover.webp',
            'storagePath' => 'cover.webp',
        ];
    }

    public static function class(): string
    {
        return ServiceWorkImage::class;
    }
}
