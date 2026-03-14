<?php

declare(strict_types=1);

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
    public ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([Gallery::LIST])]
    public string $title;

    #[Assert\NotBlank(message: 'Vous devez spécifier une description pour votre galerie', groups: ['publish'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups([Gallery::LIST])]
    public ?DateTimeInterface $publicationDatetime = null;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $status = self::STATUS_DRAFT;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([Gallery::LIST])]
    public User $author;

    /**
     * @var Collection<int, GalleryImage>
     */
    #[ORM\OneToMany(mappedBy: 'gallery', targetEntity: GalleryImage::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['creationDatetime' => 'DESC'])]
    public Collection $images;

    #[Assert\NotNull(message: 'Vous devez spécifier une image de couverture', groups: ['publish'])]
    #[ORM\OneToOne(targetEntity: GalleryImage::class, cascade: ['persist', 'remove'])]
    #[Groups([Gallery::LIST])]
    public ?GalleryImage $coverImage = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([Gallery::LIST])]
    public ?string $slug = null;

    #[ORM\OneToOne(targetEntity: ViewCache::class, cascade: ['persist', 'remove'])]
    public ?ViewCache $viewCache = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->images = new ArrayCollection();
    }

    public function addImage(GalleryImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images[] = $image;
            $image->gallery = $this;
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

    #[Groups([Gallery::LIST])]
    public function getImageCount(): int
    {
        return count($this->images);
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

    public function getViewableId(): ?string
    {
        return $this->id !== null ? (string) $this->id : null;
    }

    public function getViewableType(): string
    {
        return 'app_gallery';
    }
}
