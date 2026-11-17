<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Repository\Service\ServiceWorkImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/**
 * An image of a work (#1407), converted like a wall photo (EXIF and its GPS position gone) and streamed through the
 * service's route, so a draft's images stay its owner's.
 */
#[ORM\Entity(repositoryClass: ServiceWorkImageRepository::class)]
#[ORM\Table(name: 'service_work_image')]
#[Vich\Uploadable]
class ServiceWorkImage
{
    public const int MAX_PER_WORK = 12;

    public const string MAX_UPLOAD_SIZE = '10M';

    public const array ACCEPTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceWork::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ServiceWork $work;

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

    #[Vich\UploadableField(mapping: 'service_work_image', fileNameProperty: 'storagePath', size: 'size')]
    public ?File $uploadedFile = null;
}
