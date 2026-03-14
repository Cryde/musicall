<?php declare(strict_types=1);

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
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    /**
     * @var Collection<int, ForumCategory>
     */
    #[ORM\OneToMany(mappedBy: 'forumSource', targetEntity: ForumCategory::class)]
    public Collection $forumCategories;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[ApiProperty(identifier: true)]
    public string $slug;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->forumCategories = new ArrayCollection();
    }

    public function addForumCategory(ForumCategory $forumCategory): self
    {
        if (!$this->forumCategories->contains($forumCategory)) {
            $this->forumCategories[] = $forumCategory;
            $forumCategory->forumSource = $this;
        }

        return $this;
    }

    public function removeForumCategory(ForumCategory $forumCategory): self
    {
        $this->forumCategories->removeElement($forumCategory);

        return $this;
    }
}
