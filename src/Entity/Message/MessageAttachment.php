<?php

declare(strict_types=1);

namespace App\Entity\Message;

use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Repository\Message\MessageAttachmentRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * A Band Space object a chat message points at (#970): an agenda entry, a task, a note, a file, a
 * setlist, a song or a finance entry.
 *
 * Its own table rather than BandSpaceFileAttachment, which couples a *file* to a source and whose
 * coupling drives BandSpaceFileDeleteProcessor's 422 delete guard. A message mentioning a file in
 * passing must not make that file undeletable forever.
 *
 * `label` is the target's title, snapshotted when it was attached. That snapshot is what removes the
 * need for a delete guard here: the task can be deleted three months later and the message still
 * reads « Réparer l'ampli (supprimé) » instead of vanishing. It is also why there is no orphan prune
 * command, unlike the file version: an orphan row is harmless and pruning it would destroy the only
 * surviving record of what was linked.
 *
 * `target_id` carries no foreign key, by design: the column is polymorphic.
 */
#[ORM\Entity(repositoryClass: MessageAttachmentRepository::class)]
#[ORM\Table(name: 'message_attachment')]
#[ORM\UniqueConstraint(name: 'message_attachment_unique', columns: ['message_id', 'target_type', 'target_id'])]
#[ORM\Index(name: 'idx_message_attachment_target', columns: ['target_type', 'target_id'])]
class MessageAttachment
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

    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Message $message;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: BandSpaceSearchResultType::class)]
    public BandSpaceSearchResultType $targetType;

    #[ORM\Column(type: 'uuid')]
    public UuidInterface|string $targetId {
        get {
            return is_string($this->targetId) ? $this->targetId : $this->targetId->toString();
        }
    }

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $label;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct(
        Message $message,
        BandSpaceSearchResultType $targetType,
        string $targetId,
        string $label,
    ) {
        $this->message = $message;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->label = $label;
        $this->creationDatetime = new DateTime();
    }
}
