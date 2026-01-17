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
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: UserEmailType::class)]
    private UserEmailType $emailType;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $referenceId = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $sentDatetime;

    public function __construct()
    {
        $this->sentDatetime = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getEmailType(): UserEmailType
    {
        return $this->emailType;
    }

    public function setEmailType(UserEmailType $emailType): self
    {
        $this->emailType = $emailType;

        return $this;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(?string $referenceId): self
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getSentDatetime(): DateTimeImmutable
    {
        return $this->sentDatetime;
    }

    public function setSentDatetime(DateTimeImmutable $sentDatetime): self
    {
        $this->sentDatetime = $sentDatetime;

        return $this;
    }
}
