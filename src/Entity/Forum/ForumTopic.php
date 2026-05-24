<?php declare(strict_types=1);

namespace App\Entity\Forum;

use App\Contracts\SluggableEntityInterface;
use App\Entity\User;
use App\Repository\Forum\ForumTopicRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: ForumTopicRepository::class)]
#[ORM\Index(name: 'idx_forum_topic_title_ft', columns: ['title'], flags: ['FULLTEXT'])]
class ForumTopic implements SluggableEntityInterface
{
    final const TYPE_TOPIC_DEFAULT = 0;
    final const TYPE_TOPIC_PINNED = 1;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Forum $forum;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    public string $slug;

    #[ORM\Column(type: Types::INTEGER)]
    public int $type = self::TYPE_TOPIC_DEFAULT;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $isLocked = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $isResolved = false;

    #[ORM\ManyToOne(targetEntity: ForumPost::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?ForumPost $lastPost = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $author;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    public int $postNumber = 0;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
