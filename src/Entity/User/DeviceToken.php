<?php declare(strict_types=1);

namespace App\Entity\User;

use App\Entity\User;
use App\Enum\User\DevicePlatform;
use App\Repository\User\DeviceTokenRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * One device's FCM registration token. The token is unique across users: a phone signed into a new
 * account moves its token there, so it never notifies the previous one.
 *
 * `onDelete: CASCADE` is a safety net: accounts are anonymized, not removed, so DeleteAccountProcedure
 * deletes these rows itself.
 */
#[ORM\Entity(repositoryClass: DeviceTokenRepository::class)]
#[ORM\Table(name: 'device_token')]
class DeviceToken
{
    final public const int TOKEN_MAX_LENGTH = 512;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\Column(type: Types::STRING, length: self::TOKEN_MAX_LENGTH, unique: true)]
    public string $token;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: DevicePlatform::class)]
    public DevicePlatform $platform;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $lastSeenDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
        $this->lastSeenDatetime = $this->creationDatetime;
    }
}
