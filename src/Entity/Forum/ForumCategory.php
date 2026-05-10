<?php declare(strict_types=1);

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
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::INTEGER)]
    public int $position;

    #[ORM\ManyToOne(targetEntity: ForumSource::class, inversedBy: 'forumCategories')]
    #[ORM\JoinColumn(nullable: false)]
    public ForumSource $forumSource;

    /**
     * @var Collection<int, Forum>
     */
    #[ORM\OneToMany(targetEntity: Forum::class, mappedBy: 'forumCategory')]
    public Collection $forums;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->forums = new ArrayCollection();
    }

    public function addForum(Forum $forum): self
    {
        if (!$this->forums->contains($forum)) {
            $this->forums[] = $forum;
            $forum->forumCategory = $this;
        }

        return $this;
    }

    public function removeForum(Forum $forum): self
    {
        $this->forums->removeElement($forum);

        return $this;
    }
}
