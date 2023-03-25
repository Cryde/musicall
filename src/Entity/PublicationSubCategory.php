<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\PublicationSubCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PublicationSubCategoryRepository::class)]
#[ApiResource(operations: [
    new Get(normalizationContext: ['groups' => [PublicationSubCategory::ITEM]]),
    new GetCollection(normalizationContext: ['groups' => [PublicationSubCategory::LIST]], name: 'api_publication_sub_categories_get_collection')
])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['position' => 'ASC'])]
class PublicationSubCategory
{
    final const TYPE_PUBLICATION = 1;
    final const TYPE_COURSE = 2;

    final const TYPE_PUBLICATION_LABEL = 'publication';
    final const TYPE_COURSE_LABEL = 'course';

    final const LIST = 'PUBLICATION_CATEGORY_LIST';
    final const ITEM = 'PUBLICATION_CATEGORY_ITEM';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Groups([PublicationSubCategory::LIST, Publication::ITEM, Publication::LIST])]
    private $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([PublicationSubCategory::LIST, Publication::ITEM, Publication::LIST])]
    private $title;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Groups([PublicationSubCategory::LIST, Publication::ITEM, Publication::LIST])]
    private $slug;

    #[ORM\OneToMany(mappedBy: "subCategory", targetEntity: Publication::class, orphanRemoval: true)]
    private $publications;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups([PublicationSubCategory::LIST])]
    private $position;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups([PublicationSubCategory::LIST])]
    private $type;

    public function __construct()
    {
        $this->publications = new ArrayCollection();
    }

    #[Groups([Publication::LIST])]
    public function getTypeLabel(): string
    {
        return $this->type === self::TYPE_PUBLICATION ? self::TYPE_PUBLICATION_LABEL : self::TYPE_COURSE_LABEL;
    }

    #[Groups([Publication::LIST])]
    public function getIsCourse(): bool
    {
        return $this->type === self::TYPE_COURSE;
    }

    public function getId(): ?int
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return Collection|Publication[]
     */
    public function getPublications(): Collection
    {
        return $this->publications;
    }

    public function addPublication(Publication $publication): self
    {
        if (!$this->publications->contains($publication)) {
            $this->publications[] = $publication;
            $publication->setSubCategory($this);
        }

        return $this;
    }

    public function removePublication(Publication $publication): self
    {
        if ($this->publications->contains($publication)) {
            $this->publications->removeElement($publication);
            // set the owning side to null (unless already changed)
            if ($publication->getSubCategory() === $this) {
                $publication->setSubCategory(null);
            }
        }

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(?int $type): self
    {
        $this->type = $type;

        return $this;
    }
}
