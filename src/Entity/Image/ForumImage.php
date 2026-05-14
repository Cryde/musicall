<?php

declare(strict_types=1);

namespace App\Entity\Image;

use App\Entity\User;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class ForumImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[Assert\Image(maxSize: '4Mi', maxWidth: 4000, maxHeight: 4000)]
    #[Vich\UploadableField(mapping: 'forum_image', fileNameProperty: 'imageName', size: 'imageSize')]
    public ?File $imageFile = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $imageName = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $imageSize = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $creator;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function setImageFile(File|UploadedFile|null $image = null): void
    {
        $this->imageFile = $image;
    }
}
