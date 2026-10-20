<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: BandSpaceFileAttachmentRepository::class)]
#[ORM\Table(name: 'band_space_file_attachment')]
#[ORM\UniqueConstraint(name: 'uniq_attachment_file_source', columns: ['band_space_file_id', 'source_type', 'source_id'])]
#[ORM\Index(name: 'idx_attachment_source', columns: ['source_type', 'source_id'])]
class BandSpaceFileAttachment
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: BandSpaceFile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpaceFile $bandSpaceFile;

    #[ORM\Column(type: Types::STRING, length: 20)]
    public string $sourceType;

    #[ORM\Column(type: Types::GUID)]
    public string $sourceId;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $attachedDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $attachedBy;

    public function __construct()
    {
        $this->attachedDatetime = new DateTime();
    }
}
