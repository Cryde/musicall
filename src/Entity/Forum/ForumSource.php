<?php

declare(strict_types=1);

namespace App\Entity\Forum;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use DateTime;
use ApiPlatform\Metadata\ApiProperty;
use App\Repository\Forum\ForumSourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: ForumSourceRepository::class)]
class ForumSource
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ApiProperty(identifier: false)]
    private ?UuidInterface $id = null;
    /**
     * @var Collection<int, ForumCategory>
     */
    #[ORM\OneToMany(mappedBy: 'forumSource', targetEntity: ForumCategory::class)]
    private Collection $forumCategories;
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[ApiProperty(identifier: true)]
    private string $slug;
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $description = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->forumCategories = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id?->toString();
    }

    /**
     * @return Collection<int, ForumCategory>
     */
    public function getForumCategories(): Collection
    {
        return $this->forumCategories;
    }

    public function addForumCategory(ForumCategory $forumCategory): self
    {
        if (!$this->forumCategories->contains($forumCategory)) {
            $this->forumCategories[] = $forumCategory;
            $forumCategory->setForumSource($this);
        }

        return $this;
    }

    public function removeForumCategory(ForumCategory $forumCategory): self
    {
        // set the owning side to null (unless already changed)
        if ($this->forumCategories->removeElement($forumCategory) && $forumCategory->getForumSource() === $this) {
            $forumCategory->setForumSource(null);
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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
}
