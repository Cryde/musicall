<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\OpenApi\Model\Operation;
use DateTimeInterface;
use DateTime;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Contracts\Metric\ViewableInterface;
use App\Entity\Image\GalleryImage;
use App\Repository\GalleryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Metric\ViewCache;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GalleryRepository::class)]
#[ApiResource(operations: [
    new GetCollection(
        openapi: new Operation(tags: ['Publications']),
        paginationEnabled: false,
        normalizationContext: ['groups' => [Gallery::LIST], 'skip_null_values' => false],
        name: 'api_gallery_get_collection',
    )
])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['publicationDatetime' => OrderFilterInterface::DIRECTION_DESC])]
class Gallery implements ViewableInterface
{
    final const STATUS_ONLINE = 0;
    final const STATUS_DRAFT = 1;
    final const STATUS_PENDING = 2;

    final const LIST = 'GALLERY_LIST';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Groups([Gallery::LIST])]
    private $id;

    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([Gallery::LIST])]
    private $title;

    #[Assert\NotBlank(message: 'Vous devez spécifier une description pour votre galerie', groups: ['publish'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updateDatetime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups([Gallery::LIST])]
    private ?DateTimeInterface $publicationDatetime = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $status = self::STATUS_DRAFT;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([Gallery::LIST])]
    private User $author;

    /**
     * @var Collection<int, GalleryImage>
     */
    #[ORM\OneToMany(mappedBy: 'gallery', targetEntity: GalleryImage::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['creationDatetime' => 'DESC'])]
    private Collection $images;

    #[Assert\NotNull(message: 'Vous devez spécifier une image de couverture', groups: ['publish'])]
    #[ORM\OneToOne(targetEntity: GalleryImage::class, cascade: ['persist', 'remove'])]
    #[Groups([Gallery::LIST])]
    private ?GalleryImage $coverImage = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([Gallery::LIST])]
    private ?string $slug = null;

    #[ORM\OneToOne(targetEntity: ViewCache::class, cascade: ['persist', 'remove'])]
    private ?ViewCache $viewCache = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->images = new ArrayCollection();
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

    public function getCreationDatetime(): ?DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getUpdateDatetime(): ?DateTimeInterface
    {
        return $this->updateDatetime;
    }

    public function setUpdateDatetime(DateTimeInterface $updateDatetime): self
    {
        $this->updateDatetime = $updateDatetime;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPublicationDatetime(): ?DateTimeInterface
    {
        return $this->publicationDatetime;
    }

    public function setPublicationDatetime(?DateTimeInterface $publicationDatetime): self
    {
        $this->publicationDatetime = $publicationDatetime;

        return $this;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): self
    {
        $this->author = $author;

        return $this;
    }

    /**
     * @return Collection|GalleryImage[]
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(GalleryImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images[] = $image;
            $image->setGallery($this);
        }

        return $this;
    }

    public function removeImage(GalleryImage $image): self
    {
        if ($this->images->contains($image)) {
            $this->images->removeElement($image);
        }

        return $this;
    }

    public function getCoverImage(): ?GalleryImage
    {
        return $this->coverImage;
    }

    public function setCoverImage(?GalleryImage $coverImage): self
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
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

    public function getViewCache(): ?ViewCache
    {
        return $this->viewCache;
    }

    public function setViewCache(?ViewCache $viewCache): self
    {
        $this->viewCache = $viewCache;

        return $this;
    }

    #[Groups([Gallery::LIST])]
    public function getImageCount(): int
    {
        return count($this->images);
    }
}
