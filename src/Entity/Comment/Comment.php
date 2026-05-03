<?php declare(strict_types=1);

namespace App\Entity\Comment;

use DateTimeInterface;
use DateTime;
use App\Contracts\Metric\VotableInterface;
use App\Entity\Metric\VoteCache;
use App\Entity\User;
use App\Repository\Comment\CommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment implements VotableInterface
{
    // Kept for backwards compatibility with #[Groups] references on User and other entities
    // until the Publication / ForumPost migrations land.
    const LIST = 'COMMENT_LIST';
    const ITEM = 'COMMENT_ITEM';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    public int $id;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: CommentThread::class, inversedBy: "comments")]
    #[ORM\JoinColumn(nullable: false)]
    public CommentThread $thread;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $author;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[Assert\NotBlank(message: 'Le commentaire est vide')]
    #[ORM\Column(type: Types::TEXT)]
    public string $content {
        set(string $value) {
            $this->content = trim($value);
        }
    }

    #[ORM\OneToOne(targetEntity: VoteCache::class, cascade: ['persist', 'remove'])]
    public ?VoteCache $voteCache = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getVotableId(): ?string
    {
        return (string) $this->id;
    }

    public function getVotableType(): string
    {
        return 'app_comment';
    }
}
