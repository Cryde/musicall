<?php

declare(strict_types=1);

namespace App\Tests\Factory\Service;

use App\Entity\Service\ServiceLink;
use App\Enum\SocialPlatform;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ServiceLink>
 */
final class ServiceLinkFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'service' => ServiceFactory::new()->teaching(),
            'platform' => self::faker()->randomElement(SocialPlatform::cases()),
            'url' => self::faker()->url(),
        ];
    }

    public static function class(): string
    {
        return ServiceLink::class;
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
}
