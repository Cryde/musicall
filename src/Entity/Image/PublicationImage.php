<?php

declare(strict_types=1);

namespace App\Entity\Image;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use DateTimeImmutable;
use DateTimeInterface;
use App\Entity\Publication;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class PublicationImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    // NOTE: This is not a mapped field of entity metadata, just a simple property.
    #[Assert\Image(maxSize: '4Mi', maxWidth: 4000, maxHeight: 4000)]
    #[Vich\UploadableField(mapping: 'publication_image', fileNameProperty: 'imageName', size: 'imageSize')]
    public ?File $imageFile = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $imageName = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $imageSize = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Publication::class, inversedBy: 'images')]
    public ?Publication $publication = null;

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     */
    public function setImageFile(File|UploadedFile|null $image = null): void
    {
        $this->imageFile = $image;
        if ($image instanceof \Symfony\Component\HttpFoundation\File\File) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTime();
        }
    }
}
