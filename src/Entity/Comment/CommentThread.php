<?php declare(strict_types=1);

namespace App\Entity\Comment;

use ApiPlatform\OpenApi\Model\Operation;
use DateTimeInterface;
use DateTime;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\Publication;
use App\Repository\Comment\CommentThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CommentThreadRepository::class)]
#[ApiResource(operations: [
    new Get(
        openapi: new Operation(tags: ['Comment']),
        normalizationContext: ['groups' => [CommentThread::ITEM]],
        name: 'api_comment_threads_get_item',
    )
])]
class CommentThread
{
    final const ITEM = 'COMMENT_THREAD_ITEM';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Groups([Publication::ITEM])]
    public int $id;

    #[ORM\Column(type: Types::INTEGER)]
    public int $commentNumber = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups([CommentThread::ITEM])]
    public bool $isActive = true;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: "thread")]
    #[ORM\OrderBy(['creationDatetime' => 'DESC'])]
    public Collection $comments;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->comments = new ArrayCollection();
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments[] = $comment;
            $comment->thread = $this;
        }

        return $this;
    }
}
