<?php declare(strict_types=1);

namespace App\Entity\Message;

use App\Entity\User;
use App\Repository\Message\MessageFileRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/**
 * An image or a voice note sent in a direct conversation (#1247), the band chat's media without a
 * band: owned by its sender, kept as long as its message, and counted against no quota, since the
 * conversion already bounds what one message can store.
 */
#[ORM\Entity(repositoryClass: MessageFileRepository::class)]
#[ORM\Table(name: 'message_file')]
#[Vich\Uploadable]
class MessageFile
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: MessageThread::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public MessageThread $thread;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $uploadedBy;

    #[ORM\Column(type: Types::STRING, length: 191)]
    public string $mimeType;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $size = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $originalName;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $storagePath = null;

    #[Vich\UploadableField(mapping: 'message_file', fileNameProperty: 'storagePath', size: 'size')]
    public ?File $uploadedFile = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}
