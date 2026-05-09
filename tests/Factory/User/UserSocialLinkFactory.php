<?php

declare(strict_types=1);

namespace App\Tests\Factory\User;

use App\Entity\User\UserSocialLink;
use App\Enum\SocialPlatform;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<UserSocialLink>
 */
final class UserSocialLinkFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'platform' => SocialPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/@testuser',
        ];
    }

    public function withPlatform(SocialPlatform $platform): static
    {
        return $this->with(['platform' => $platform]);
    }

    public function withUrl(string $url): static
    {
        return $this->with(['url' => $url]);
    }

    public static function class(): string
    {
        return UserSocialLink::class;
    }
}
