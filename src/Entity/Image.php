<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 */
class Image
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    /**
     * NOTE: This is not a mapped field of entity metadata, just a simple property.
     * @Assert\Image(
     *     maxWidth=1501,
     *     maxHeight=1501
     * )
     *
     * @var File
     */
    private $imageFile;
    /**
     * @ORM\Column(type="string", length=255)
     *
     * @var string
     */
    private $imageName;
    /**
     * @ORM\Column(type="integer")
     *
     * @var integer
     */
    private $imageSize;
    /**
     * @ORM\Column(type="datetime")
     *
     * @var \DateTimeInterface
     */
    private $creationDatetime;
    /**
     * @ORM\Column(type="datetime")
     *
     * @var \DateTimeInterface
     */
    private $updateDatetime;

    public function __construct()
    {
        $this->creationDatetime = new \DateTimeImmutable();
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
     * @param File|\Symfony\Component\HttpFoundation\File\UploadedFile $image
     */
    public function setImageFile(?File $image = null): void
    {
        $this->imageFile = $image;
        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updateDatetime = new \DateTimeImmutable();
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

    /**
     * @return \DateTimeInterface
     */
    public function getCreationDatetime(): \DateTimeInterface
    {
        return $this->creationDatetime;
    }

    /**
     * @param \DateTimeInterface $creationDatetime
     *
     * @return Image
     */
    public function setCreationDatetime(\DateTimeInterface $creationDatetime): Image
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdateDatetime(): \DateTimeInterface
    {
        return $this->updateDatetime;
    }

    /**
     * @param \DateTimeInterface $updateDatetime
     *
     * @return Image
     */
    public function setUpdateDatetime(\DateTimeInterface $updateDatetime): Image
    {
        $this->updateDatetime = $updateDatetime;

        return $this;
    }
}
