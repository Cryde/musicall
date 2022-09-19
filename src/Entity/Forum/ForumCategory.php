<?php

namespace App\Entity\Forum;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\Forum\ForumCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ForumCategoryRepository::class)]
#[ApiResource(
    collectionOperations: [
        'get' => ['normalization_context' => ['groups' => [ForumCategory::LIST]]],
    ],
    itemOperations: ['get'],
)]
#[ApiFilter(SearchFilter::class, properties: ['forumSource.slug' => SearchFilterInterface::STRATEGY_EXACT])]
#[ApiFilter(OrderFilter::class, properties: ['position' => 'ASC', 'forums.position' => 'ASC'])]
class ForumCategory
{
    final const LIST = 'FORUM_CATEGORY_LIST';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'guid')]
    #[ORM\GeneratedValue(strategy: 'UUID')]
    #[Groups([ForumCategory::LIST, Forum::ITEM])]
    private $id;
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups([ForumCategory::LIST, Forum::ITEM])]
    private $title;
    #[ORM\Column(type: 'datetime')]
    private $creationDatetime;
    #[ORM\Column(type: 'integer')]
    private $position;
    #[ORM\ManyToOne(targetEntity: ForumSource::class, inversedBy: 'forumCategories')]
    #[ORM\JoinColumn(nullable: false)]
    private $forumSource;
    #[ORM\OneToMany(mappedBy: 'forumCategory', targetEntity: Forum::class)]
    #[Groups([ForumCategory::LIST])]
    private $forums;

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
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

    public function getCreationDatetime(): \DateTime
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(\DateTimeInterface $creationDatetime): self
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
        if ($this->forums->removeElement($forum)) {
            // set the owning side to null (unless already changed)
            if ($forum->getForumCategory() === $this) {
                $forum->setForumCategory(null);
            }
        }

        return $this;
    }
}
