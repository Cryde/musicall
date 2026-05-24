<?php declare(strict_types=1);

namespace App\Entity\Forum;

use ApiPlatform\Metadata\ApiProperty;
use App\Contracts\Metric\VotableInterface;
use App\Entity\Metric\VoteCache;
use App\Entity\User;
use App\Repository\Forum\ForumPostRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ForumPostRepository::class)]
#[ORM\Index(name: 'idx_forum_post_content_ft', columns: ['content'], flags: ['FULLTEXT'])]
class ForumPost implements VotableInterface
{
    final public const LIST = 'FORUM_POST_LIST';

    final public const MIN_MESSAGE_LENGTH = 10;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: ForumPost::MIN_MESSAGE_LENGTH)]
    #[ORM\Column(type: Types::TEXT)]
    public string $content;

    #[Assert\NotBlank]
    #[ORM\ManyToOne(targetEntity: ForumTopic::class)]
    #[ORM\JoinColumn(nullable: false)]
    public ForumTopic $topic;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(genId: false)]
    public User $creator;

    #[ORM\OneToOne(targetEntity: VoteCache::class, cascade: ['persist', 'remove'])]
    public ?VoteCache $voteCache = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getVotableId(): ?string
    {
        /** @var string|null $id */
        $id = $this->id;

        return $id;
    }

    public function getVotableType(): string
    {
        return 'app_forum_post';
    }
}
