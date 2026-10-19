<?php

declare(strict_types=1);

namespace App\Entity\Message;

use App\Entity\User;
use App\Repository\Message\MessageRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Index(name: 'idx_message_thread_creation', columns: ['thread_id', 'creation_datetime'])]
#[ORM\Index(name: 'idx_message_thread_pinned', columns: ['thread_id', 'pinned_datetime'])]
class Message
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $author;

    #[ORM\ManyToOne(targetEntity: MessageThread::class, inversedBy: "messages")]
    #[ORM\JoinColumn(nullable: false)]
    public MessageThread $thread;

    #[ORM\Column(type: Types::TEXT)]
    public string $content;

    /**
     * Null until the author edits the message (#966), which is what the « modifié » marker reads.
     *
     * Immutable where creationDatetime above is mutable: that one predates the convention and is
     * written by MessageSenderProcedure, so it stays as it is rather than being migrated here.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updateDatetime = null;

    /**
     * A tombstone rather than a hard delete (#967): the row is what holds the conversation together,
     * and message_thread.last_message_id still points at it. Stamping this empties the content and
     * removes the mention rows, so nothing of what was written survives.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $deletionDatetime = null;

    /**
     * When this message was pinned to the top of its channel, null when it is not (#969).
     *
     * Two columns on the message rather than a pin table: a message is pinned at most once, so a
     * table would be a join for nothing, and the pinned list stays a plain index read.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $pinnedDatetime = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?User $pinnedBy = null;

    /**
     * The band space file holding the image this message carries (#973).
     *
     * No foreign key on purpose: the file can be purged from the Files trash while the message stays,
     * and the id surviving it is what lets the bubble say « Image supprimée » instead of going blank.
     */
    #[ORM\Column(type: Types::GUID, nullable: true)]
    public ?string $imageFileId = null;

    /** The band space file holding the voice note this message carries (#974), no foreign key either. */
    #[ORM\Column(type: Types::GUID, nullable: true)]
    public ?string $voiceNoteFileId = null;

    /** Measured by ffprobe on the stored file: a MediaRecorder WebM carries no duration of its own. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    public ?int $voiceNoteDurationSeconds = null;

    /**
     * The waveform the player draws: ChatVoiceNoteConverter::PEAK_COUNT loudness values, 0 to 255,
     * measured once at upload so no reader has to download the audio to see its shape.
     *
     * @var list<int>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $voiceNotePeaks = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function isDeleted(): bool
    {
        return $this->deletionDatetime instanceof DateTimeImmutable;
    }
}
