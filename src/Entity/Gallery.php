<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeInterface;
use DateTime;
use App\Contracts\Metric\ViewableInterface;
use App\Entity\Image\GalleryImage;
use App\Repository\GalleryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Metric\ViewCache;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GalleryRepository::class)]
class Gallery implements ViewableInterface
{
    final const STATUS_ONLINE = 0;
    final const STATUS_DRAFT = 1;
    final const STATUS_PENDING = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    public ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    #[Assert\NotBlank(message: 'Vous devez spécifier une description pour votre galerie', groups: ['publish'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $publicationDatetime = null;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $status = self::STATUS_DRAFT;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $author;

    /**
     * @var Collection<int, GalleryImage>
     */
    #[ORM\OneToMany(targetEntity: GalleryImage::class, mappedBy: 'gallery', cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['creationDatetime' => 'DESC'])]
    public Collection $images;

    #[Assert\NotNull(message: 'Vous devez spécifier une image de couverture', groups: ['publish'])]
    #[ORM\OneToOne(targetEntity: GalleryImage::class, cascade: ['persist', 'remove'])]
    public ?GalleryImage $coverImage = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
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

    public function getImageCount(): int
    {
        return count($this->images);
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
