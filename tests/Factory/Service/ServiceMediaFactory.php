<?php

declare(strict_types=1);

namespace App\Tests\Factory\Service;

use App\Entity\Service\ServiceMedia;
use App\Enum\Musician\MediaPlatform;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ServiceMedia>
 */
final class ServiceMediaFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'service' => ServiceFactory::new()->teaching(),
            'platform' => MediaPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'embedId' => 'dQw4w9WgXcQ',
            'title' => 'Sample Video',
            'position' => 0,
            'creationDatetime' => new DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ];
    }

    public static function class(): string
    {
        return ServiceMedia::class;
    }
}
