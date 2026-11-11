<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Entity\User;
use App\Enum\User\ClientPlatform;
use App\Repository\User\UserDailyActivityRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * A member was active on this platform on this day (#1370): one row however many requests, written
 * by UserActivityListener. Nothing else is kept, no IP and no user agent; purged after 13 months.
 */
#[ORM\Entity(repositoryClass: UserDailyActivityRepository::class)]
#[ORM\Table(name: 'user_daily_activity')]
#[ORM\UniqueConstraint(name: 'user_daily_activity_unique', columns: ['user_id', 'day', 'platform'])]
#[ORM\Index(name: 'idx_user_daily_activity_day_platform_user', columns: ['day', 'platform', 'user_id'])]
class UserDailyActivity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public DateTimeImmutable $day;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ClientPlatform::class)]
    public ClientPlatform $platform;

    // The last version seen that day; null on the web.
    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    public ?string $appVersion = null;
}
