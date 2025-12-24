<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: BandSpaceMembershipRepository::class)]
#[ORM\Table(name: 'band_space_membership')]
#[ORM\UniqueConstraint(name: 'unique_band_space_user', columns: ['band_space_id', 'user_id'])]
class BandSpaceMembership
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public $id {
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

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
