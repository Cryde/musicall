<?php

declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\Song;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Song>
 */
final class SongFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'title' => self::faker()->sentence(3),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public function withLyrics(string $content, int $version = 1): static
    {
        return $this->afterPersist(static function (Song $song) use ($content, $version): void {
            LyricsFactory::createOne([
                'bandSpace' => $song->bandSpace,
                'song' => $song,
                'title' => $song->title,
                'content' => $content,
                'version' => $version,
            ]);
        });
    }

    public static function class(): string
    {
        return Song::class;
    }
}
