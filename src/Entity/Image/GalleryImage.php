<?php declare(strict_types=1);

namespace App\Entity\Image;

use App\Entity\Gallery;
use App\Repository\GalleryImageRepository;
use DateTimeInterface;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: GalleryImageRepository::class)]
#[Vich\Uploadable]
class GalleryImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    // NOTE: This is not a mapped field of entity metadata, just a simple property.
    #[Assert\Image(maxWidth: 4000, maxHeight: 4000)]
    #[Vich\UploadableField(mapping: 'gallery_image', fileNameProperty: 'imageName', size: 'imageSize')]
    public ?File $imageFile = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $imageName = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $imageSize = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Gallery::class, inversedBy: "images")]
    #[ORM\JoinColumn(nullable: false)]
    public Gallery $gallery;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
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
            $this->updatedAt = new \DateTime();
        }
    }
}
