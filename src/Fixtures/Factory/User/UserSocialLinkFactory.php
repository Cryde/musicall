<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\User;

use App\Entity\User\UserSocialLink;
use App\Enum\SocialPlatform;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<UserSocialLink>
 */
final class UserSocialLinkFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        $platform = self::faker()->randomElement(SocialPlatform::cases());

        return [
            'platform' => $platform,
            'url' => $this->generateUrlForPlatform($platform),
        ];
    }

    private function generateUrlForPlatform(SocialPlatform $platform): string
    {
        $username = self::faker()->userName();

        return match ($platform) {
            SocialPlatform::YOUTUBE => "https://www.youtube.com/@{$username}",
            SocialPlatform::SOUNDCLOUD => "https://soundcloud.com/{$username}",
            SocialPlatform::INSTAGRAM => "https://www.instagram.com/{$username}",
            SocialPlatform::FACEBOOK => "https://www.facebook.com/{$username}",
            SocialPlatform::TWITTER => "https://twitter.com/{$username}",
            SocialPlatform::TIKTOK => "https://www.tiktok.com/@{$username}",
            SocialPlatform::SPOTIFY => "https://open.spotify.com/artist/" . self::faker()->uuid(),
            SocialPlatform::BANDCAMP => "https://{$username}.bandcamp.com",
            SocialPlatform::WEBSITE => self::faker()->url(),
        };
    }

    public static function class(): string
    {
        return UserSocialLink::class;
    }
}
