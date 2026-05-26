<?php declare(strict_types=1);

namespace App\Entity\Notification;

use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Repository\Notification\NotificationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\Index(name: 'idx_notification_recipient_read', columns: ['recipient_id', 'read_datetime'])]
#[ORM\Index(name: 'idx_notification_recipient_created', columns: ['recipient_id', 'creation_datetime'])]
class Notification
{
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
    public User $recipient;

    #[ORM\Column(type: Types::STRING, length: 40, enumType: NotificationType::class)]
    public NotificationType $type;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    public array $payload = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $readDatetime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}
