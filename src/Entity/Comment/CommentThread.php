<?php

namespace App\Entity\Comment;

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
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CommentThreadRepository::class)]
#[ApiResource(operations: [
    new Get(normalizationContext: ['groups' => [CommentThread::ITEM]], name: 'api_comment_threads_get_item')
])]
class CommentThread
{
    final const ITEM = 'COMMENT_THREAD_ITEM';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Groups([Publication::ITEM])]
    private $id;

    #[ORM\Column(type: Types::INTEGER)]
    private int $commentNumber = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups([CommentThread::ITEM])]
    private bool $isActive = true;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(mappedBy: "thread", targetEntity: Comment::class)]
    #[ORM\OrderBy(['creationDatetime' => 'DESC'])]
    private Collection $comments;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommentNumber(): ?int
    {
        return $this->commentNumber;
    }

    public function setCommentNumber(int $commentNumber): self
    {
        $this->commentNumber = $commentNumber;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection|Comment[]
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments[] = $comment;
            $comment->setThread($this);
        }

        return $this;
    }

    public function getCreationDatetime(): ?DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(?DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }
}
