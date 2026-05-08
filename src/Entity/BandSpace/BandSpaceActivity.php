<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: BandSpaceActivityRepository::class)]
#[ORM\Table(name: 'band_space_activity')]
#[ORM\Index(columns: ['band_space_id', 'creation_datetime'], name: 'idx_band_space_activity_feed')]
#[ORM\Index(columns: ['band_space_id', 'module', 'resource_id', 'creation_datetime'], name: 'idx_band_space_activity_resource')]
class BandSpaceActivity
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

    #[ORM\Column(type: Types::STRING, length: 20, nullable: false, enumType: BandSpaceModule::class)]
    public BandSpaceModule $module;

    #[ORM\Column(type: "uuid", nullable: true)]
    public ?UuidInterface $resourceId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $actor = null;

    #[ORM\Column(type: Types::STRING, length: 30)]
    public string $type;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $payload = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
