<?php

namespace App\Entity;

use App\Entity\Image\GalleryImage;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\GalleryRepository")
 */
class Gallery
{
    const STATUS_ONLINE = 0;
    const STATUS_DRAFT = 1;
    const STATUS_PENDING = 2;
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;
    /**
     * @ORM\Column(type="string", length=255)
     *
     * @Assert\NotBlank()
     * @Assert\Length(max="200")
     */
    private $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     *
     * @Assert\NotBlank(groups={"publish"}, message="Vous devez spécifier une description pour votre galerie")
     */
    private $description;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDatetime;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $updateDatetime;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $publicationDatetime;
    /**
     * @ORM\Column(type="smallint")
     */
    private $status;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     *
     * @Assert\NotNull()
     */
    private $author;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Image\GalleryImage", mappedBy="gallery")
     * @ORM\OrderBy({"creationDatetime" = "DESC"})
     */
    private $images;

    /**
     * @Assert\NotNull(groups={"publish"}, message="Vous devez spécifier une image de couverture")
     * @ORM\OneToOne(targetEntity="App\Entity\Image\GalleryImage", cascade={"persist", "remove"})
     */
    private $coverImage;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $slug;

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
        $this->status = self::STATUS_DRAFT;
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

    public function getCreationDatetime(): ?\DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(\DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getUpdateDatetime(): ?\DateTimeInterface
    {
        return $this->updateDatetime;
    }

    public function setUpdateDatetime(\DateTimeInterface $updateDatetime): self
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

    public function getPublicationDatetime(): ?\DateTimeInterface
    {
        return $this->publicationDatetime;
    }

    public function setPublicationDatetime(?\DateTimeInterface $publicationDatetime): self
    {
        $this->publicationDatetime = $publicationDatetime;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
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
            // set the owning side to null (unless already changed)
            if ($image->getGallery() === $this) {
                $image->setGallery(null);
            }
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
}
