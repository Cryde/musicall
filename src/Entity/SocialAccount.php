<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SocialAccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SocialAccountRepository::class)]
#[ORM\Table(name: 'user_social_account')]
#[ORM\UniqueConstraint(name: 'user_social_account_provider_unique', columns: ['provider', 'provider_id'])]
class SocialAccount
{
    final public const PROVIDER_GOOGLE = 'google';
    final public const PROVIDER_APPLE = 'apple';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'socialAccounts')]
    #[ORM\JoinColumn(nullable: false)]
    public User $user;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $provider;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $providerId;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $email = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $creationDatetime;

    // Apple only (#1269): what is revoked when the account is deleted, and the client id it was
    // issued to, since Apple refuses a revocation sent under any other.
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    public ?string $refreshToken = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $refreshTokenClientId = null;

    public function __construct()
    {
        $this->creationDatetime = new \DateTimeImmutable();
    }
}
