<?php

declare(strict_types=1);

namespace App\Entity\Forum;

use App\Repository\Forum\ForumCategoryRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: ForumCategoryRepository::class)]
class ForumCategory
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;
    #[ORM\Column(type: Types::INTEGER)]
    private int $position;
    #[ORM\ManyToOne(targetEntity: ForumSource::class, inversedBy: 'forumCategories')]
    #[ORM\JoinColumn(nullable: false)]
    private ForumSource $forumSource;
    /**
     * @var Collection<int, Forum>
     */
    #[ORM\OneToMany(mappedBy: 'forumCategory', targetEntity: Forum::class)]
    private Collection $forums;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->forums = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id?->toString();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getCreationDatetime(): DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getForumSource(): ?ForumSource
    {
        return $this->forumSource;
    }

    public function setForumSource(?ForumSource $forumSource): self
    {
        $this->forumSource = $forumSource;

        return $this;
    }

    /**
     * @return Collection<int, Forum>
     */
    public function getForums(): Collection
    {
        return $this->forums;
    }

    public function addForum(Forum $forum): self
    {
        if (!$this->forums->contains($forum)) {
            $this->forums[] = $forum;
            $forum->setForumCategory($this);
        }

        return $this;
    }

    public function removeForum(Forum $forum): self
    {
        // set the owning side to null (unless already changed)
        if ($this->forums->removeElement($forum) && $forum->getForumCategory() === $this) {
            $forum->setForumCategory(null);
        }

        return $this;
    }
}
