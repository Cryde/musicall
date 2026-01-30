<?php

declare(strict_types=1);

namespace App\Tests\Factory\Teacher;

use App\Entity\Teacher\TeacherProfileMedia;
use App\Enum\Musician\MediaPlatform;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TeacherProfileMedia>
 */
final class TeacherProfileMediaFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'teacherProfile' => TeacherProfileFactory::new(),
            'platform' => MediaPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'embedId' => 'dQw4w9WgXcQ',
            'title' => 'Sample Video',
            'position' => 0,
            'creationDatetime' => new DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ];
    }

    public function withYoutube(string $embedId, string $title = 'YouTube Video'): self
    {
        return $this->with([
            'platform' => MediaPlatform::YOUTUBE,
            'url' => "https://www.youtube.com/watch?v={$embedId}",
            'embedId' => $embedId,
            'title' => $title,
        ]);
    }

    public function withSoundcloud(string $embedId, string $title = 'Soundcloud Track'): self
    {
        return $this->with([
            'platform' => MediaPlatform::SOUNDCLOUD,
            'url' => "https://soundcloud.com/track/{$embedId}",
            'embedId' => $embedId,
            'title' => $title,
        ]);
    }

    public function withPosition(int $position): self
    {
        return $this->with(['position' => $position]);
    }

    public static function class(): string
    {
        return TeacherProfileMedia::class;
    }
}
