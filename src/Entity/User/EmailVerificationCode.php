<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Entity\User;
use App\Repository\User\EmailVerificationCodeRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: EmailVerificationCodeRepository::class)]
#[ORM\Table(name: 'email_verification_code')]
class EmailVerificationCode
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
    public User $user;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $hashedCode;

    #[ORM\Column(type: Types::INTEGER)]
    public int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $expirationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $usedDatetime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        return $this->expirationDatetime < new DateTimeImmutable();
    }

    public function isUsed(): bool
    {
        return $this->usedDatetime instanceof \DateTimeImmutable;
    }

    public function hasReachedMaxAttempts(): bool
    {
        return $this->attempts >= 5;
    }
}
