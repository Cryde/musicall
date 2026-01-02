<?php

namespace App\Entity\Forum;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\OpenApi\Model\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use DateTime;
use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\Forum\ForumCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ForumCategoryRepository::class)]
#[ApiResource(operations: [
    new Get(openapi: new Operation(tags: ['Forum']),),
])]
class ForumCategory
{
    final const LIST = 'FORUM_CATEGORY_LIST';

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([Forum::ITEM])]
    private $id;
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([Forum::ITEM])]
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
    #[Groups([ForumCategory::LIST])]
    private Collection $forums;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->forums = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
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
     * @return Collection|Forum[]
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
