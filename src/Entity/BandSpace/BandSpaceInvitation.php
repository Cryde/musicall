<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Enum\BandSpace\InvitationStatus;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: BandSpaceInvitationRepository::class)]
#[ORM\Table(name: 'band_space_invitation')]
class BandSpaceInvitation
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: BandSpace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpace $bandSpace;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $invitedBy;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $email;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $existingUser = null;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    public string $token;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: InvitationStatus::class)]
    public InvitationStatus $status = InvitationStatus::Pending;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $expirationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->expirationDatetime = (new DateTime())->modify('+7 days');
    }

    public function isExpired(): bool
    {
        return $this->expirationDatetime < new DateTime();
    }
}
