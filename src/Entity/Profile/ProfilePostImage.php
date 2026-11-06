<?php

declare(strict_types=1);

namespace App\Entity\Profile;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/** A photo of a wall post (#1316), converted like a chat image and served only through the access-checked route. */
#[ORM\Entity]
#[ORM\Table(name: 'profile_post_image')]
#[Vich\Uploadable]
class ProfilePostImage
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: ProfilePost::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ProfilePost $post;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $position = 0;

    #[ORM\Column(type: Types::STRING, length: 191)]
    public string $mimeType;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $size = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $originalName;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $storagePath = null;

    #[Vich\UploadableField(mapping: 'profile_post_image', fileNameProperty: 'storagePath', size: 'size')]
    public ?File $uploadedFile = null;
}
