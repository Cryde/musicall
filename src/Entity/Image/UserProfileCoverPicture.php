<?php

declare(strict_types=1);

namespace App\Entity\Image;

use App\Entity\User\UserProfile;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class UserProfileCoverPicture
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[Assert\NotNull]
    #[Assert\Image(maxSize: '4Mi', minWidth: 800, maxWidth: 4000, maxHeight: 2000, minHeight: 200)]
    #[Vich\UploadableField(mapping: 'user_cover_picture', fileNameProperty: 'imageName', size: 'imageSize')]
    public ?File $imageFile = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $imageName = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $imageSize = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updatedAt = null;

    #[ORM\OneToOne(targetEntity: UserProfile::class)]
    public ?UserProfile $profile = null;

    public function setImageFile(?File $image = null): static
    {
        $this->imageFile = $image;
        if (null !== $image) {
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }
}
