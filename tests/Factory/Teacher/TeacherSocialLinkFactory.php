<?php

declare(strict_types=1);

namespace App\Tests\Factory\Teacher;

use App\Entity\Teacher\TeacherSocialLink;
use App\Enum\SocialPlatform;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TeacherSocialLink>
 */
final class TeacherSocialLinkFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'teacherProfile' => TeacherProfileFactory::new(),
            'platform' => self::faker()->randomElement(SocialPlatform::cases()),
            'url' => self::faker()->url(),
        ];
    }

    public static function class(): string
    {
        return TeacherSocialLink::class;
    }

    public function asYoutube(): self
    {
        return $this->with([
            'platform' => SocialPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/@teacher',
        ]);
    }

    public function asInstagram(): self
    {
        return $this->with([
            'platform' => SocialPlatform::INSTAGRAM,
            'url' => 'https://www.instagram.com/teacher',
        ]);
    }

    public function asWebsite(): self
    {
        return $this->with([
            'platform' => SocialPlatform::WEBSITE,
            'url' => 'https://www.teacher-music.com',
        ]);
    }
}
