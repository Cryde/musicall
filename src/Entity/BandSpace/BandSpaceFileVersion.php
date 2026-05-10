<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: BandSpaceFileVersionRepository::class)]
#[ORM\Table(name: 'band_space_file_version')]
#[ORM\UniqueConstraint(name: 'unique_band_space_file_version_number', columns: ['band_space_file_id', 'version_number'])]
#[Vich\Uploadable]
class BandSpaceFileVersion
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

    #[ORM\ManyToOne(targetEntity: BandSpaceFile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpaceFile $bandSpaceFile;

    #[ORM\Column(type: Types::INTEGER)]
    public int $versionNumber = 1;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $createdBy = null;

    #[ORM\Column(type: Types::STRING, length: 191)]
    public string $mimeType;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $size = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $storagePath = null;

    #[Vich\UploadableField(mapping: 'band_space_file', fileNameProperty: 'storagePath', size: 'size')]
    public ?File $uploadedFile = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function setUploadedFile(?File $file = null): void
    {
        $this->uploadedFile = $file;
        if ($file instanceof \Symfony\Component\HttpFoundation\File\File) {
            $this->updateDatetime = new DateTime();
        }
    }
}
