<?php

namespace App\Entity\Image;

use DateTimeInterface;
use DateTime;
use DateTimeImmutable;
use App\Entity\Gallery;
use App\Repository\GalleryImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: GalleryImageRepository::class)]
#[Vich\Uploadable]
class GalleryImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private $id;

    // NOTE: This is not a mapped field of entity metadata, just a simple property.
    #[Assert\Image(maxWidth: 4000, maxHeight: 4000)]
    #[Vich\UploadableField(mapping: 'gallery_image', fileNameProperty: 'imageName', size: 'imageSize')]
    private $imageFile;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private $imageName;

    #[ORM\Column(type: Types::INTEGER)]
    private $imageSize;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private $updatedAt;

    #[ORM\ManyToOne(targetEntity: Gallery::class, inversedBy: "images")]
    #[ORM\JoinColumn(nullable: false)]
    private $gallery;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|UploadedFile $image
     */
    public function setImageFile(?File $image = null): void
    {
        $this->imageFile = $image;
        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): void
    {
        $this->imageName = $imageName;
    }

    public function getImageSize(): ?int
    {
        return $this->imageSize;
    }

    public function setImageSize(?int $imageSize): void
    {
        $this->imageSize = $imageSize;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getGallery(): ?Gallery
    {
        return $this->gallery;
    }

    public function setGallery(?Gallery $gallery): self
    {
        $this->gallery = $gallery;

        return $this;
    }
}
