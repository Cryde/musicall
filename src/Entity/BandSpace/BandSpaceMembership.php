<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\Attribute\Instrument;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: BandSpaceMembershipRepository::class)]
#[ORM\Table(name: 'band_space_membership')]
#[ORM\UniqueConstraint(name: 'unique_band_space_user', columns: ['band_space_id', 'user_id'])]
class BandSpaceMembership
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

    #[ORM\ManyToOne(targetEntity: BandSpace::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpace $bandSpace;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\Column(type: Types::STRING, nullable: false, enumType: Role::class)]
    public Role $role = Role::User;

    #[ORM\Column(type: Types::STRING, nullable: false, enumType: MembershipStatus::class)]
    public MembershipStatus $status = MembershipStatus::Active;

    /**
     * The name printed on documents that leave the band, a tech rider above all. Usernames are
     * login handles and a venue should not be reading them.
     *
     * Per membership rather than on User: it is this band's choice of how to name someone, and
     * the same person may go by something else elsewhere. Null falls back to the username.
     */
    #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
    public ?string $stageName = null;

    /**
     * What this member plays in this band. Many to many because one person routinely holds more
     * than one line on a rider: bass plus backing vocals is a single member, two instruments.
     *
     * Points at the shared Instrument catalogue rather than free text, so a rider says "Batterie"
     * the same way everywhere and a future export has a known set of values.
     *
     * @var Collection<int, Instrument>
     */
    #[ORM\ManyToMany(targetEntity: Instrument::class)]
    #[ORM\JoinTable(name: 'band_space_membership_instrument')]
    #[ORM\JoinColumn(name: 'membership_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'instrument_id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    public Collection $instruments;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $leftDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->instruments = new ArrayCollection();
    }

    /** What a document should call this member. */
    public function displayName(): string
    {
        return $this->stageName ?? $this->user->username;
    }
}
