<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Entity\User;
use App\Enum\User\UserEmailType;
use App\Repository\User\UserEmailLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: UserEmailLogRepository::class)]
#[ORM\Index(columns: ['user_id', 'email_type'], name: 'idx_user_email_type')]
class UserEmailLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: UserEmailType::class)]
    public UserEmailType $emailType;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    public ?string $referenceId = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $sentDatetime;

    public function __construct()
    {
        $this->sentDatetime = new DateTimeImmutable();
    }
}
