<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\BandSpaceFileTagRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: BandSpaceFileTagRepository::class)]
#[ORM\Table(name: 'band_space_file_tag')]
#[ORM\Index(name: 'idx_band_space_file_tag_band', columns: ['band_space_id'])]
#[ORM\UniqueConstraint(name: 'unique_band_space_file_tag_name', columns: ['band_space_id', 'name'])]
class BandSpaceFileTag
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

    #[ORM\ManyToOne(targetEntity: BandSpace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpace $bandSpace;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name;

    #[ORM\Column(type: Types::STRING, length: 7, nullable: true)]
    public ?string $colorHex = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
