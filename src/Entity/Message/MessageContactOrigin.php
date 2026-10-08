<?php declare(strict_types=1);

namespace App\Entity\Message;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Teacher\TeacherProfile;
use App\Enum\Message\ContactOriginType;
use App\Repository\Message\MessageContactOriginRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * The announce or teacher profile a direct message was sent from (#998), so a conversation still
 * says why it exists weeks later.
 *
 * The fields below the links are a snapshot taken at send time: an announce is deleted once its
 * author has found someone, which is exactly when the conversation still matters, so the link alone
 * would go null at the worst moment.
 */
#[ORM\Entity(repositoryClass: MessageContactOriginRepository::class)]
#[ORM\Table(name: 'message_contact_origin')]
class MessageContactOrigin
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

    #[ORM\OneToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    public Message $message;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ContactOriginType::class)]
    public ContactOriginType $type;

    #[ORM\ManyToOne(targetEntity: MusicianAnnounce::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?MusicianAnnounce $musicianAnnounce = null;

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?TeacherProfile $teacherProfile = null;

    /** MusicianAnnounce::TYPE_MUSICIAN or TYPE_BAND, for an announce only. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    public ?int $announceType = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    public array $instruments = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    public array $styles = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $locationName = null;
}
