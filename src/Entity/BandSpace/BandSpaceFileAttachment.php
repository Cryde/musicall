<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: BandSpaceFileAttachmentRepository::class)]
#[ORM\Table(name: 'band_space_file_attachment')]
#[ORM\UniqueConstraint(name: 'uniq_attachment_file_source', columns: ['band_space_file_id', 'source_type', 'source_id'])]
#[ORM\Index(name: 'idx_attachment_source', columns: ['source_type', 'source_id'])]
class BandSpaceFileAttachment
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

    #[ORM\Column(type: Types::STRING, length: 20)]
    public string $sourceType;

    #[ORM\Column(type: 'uuid')]
    public UuidInterface|string $sourceId {
        get {
            return is_string($this->sourceId) ? $this->sourceId : $this->sourceId->toString();
        }
    }

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $attachedDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $attachedBy = null;

    public function __construct()
    {
        $this->attachedDatetime = new DateTime();
    }
}
