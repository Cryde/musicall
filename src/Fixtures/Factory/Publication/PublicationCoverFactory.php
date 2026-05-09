<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Publication;

use App\Entity\Image\PublicationCover;
use App\Fixtures\Publication\FakeImageGeneratorStory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<PublicationCover>
 */
final class PublicationCoverFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        $randomImage = FakeImageGeneratorStory::getRandom(FakeImageGeneratorStory::RANDOM_PUBLICATION_COVER);
        return [
            'imageSize' => $randomImage[1],
            'updatedAt' => self::faker()->dateTime(),
            'imageName' => $randomImage[0],
        ];
    }

    public static function class(): string
    {
        return PublicationCover::class;
    }
}
