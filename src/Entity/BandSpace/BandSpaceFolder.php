<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: BandSpaceFolderRepository::class)]
#[ORM\Table(name: 'band_space_folder')]
#[ORM\Index(name: 'idx_band_space_folder_parent', columns: ['band_space_id', 'parent_id'])]
#[ORM\UniqueConstraint(name: 'unique_band_space_folder_sibling_name', columns: ['band_space_id', 'parent_id', 'name'])]
class BandSpaceFolder
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: BandSpace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpace $bandSpace;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    public ?self $parent = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $createdBy;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
