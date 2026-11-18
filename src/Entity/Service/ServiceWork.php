<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Enum\Musician\MediaPlatform;
use App\Repository\Service\ServiceWorkRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SortDirection;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/** One of a service's « Réalisations » (#1407): what was made, for whom, and how to see it. */
#[ORM\Entity(repositoryClass: ServiceWorkRepository::class)]
#[ORM\Table(name: 'service_work')]
class ServiceWork
{
    public const int MAX_PER_SERVICE = 30;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'works')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Service $service;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $position = 0;

    /** « Satellites », « Site de Mona Lisa Overdrive ». */
    #[ORM\Column(type: Types::STRING, length: 120)]
    public string $title;

    /** « Mona Lisa Overdrive, EP »: free text, it names no member (#1407). */
    #[ORM\Column(type: Types::STRING, length: 160, nullable: true)]
    public ?string $credit = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    public ?int $year = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    public ?string $linkUrl = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: MediaPlatform::class)]
    public ?MediaPlatform $mediaPlatform = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    public ?string $mediaUrl = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $mediaEmbedId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $mediaTitle = null;

    /**
     * Eager: a page shows every work with its images, so they load in one batch rather than one query a work.
     *
     * @var Collection<int, ServiceWorkImage>
     */
    #[ORM\OneToMany(targetEntity: ServiceWorkImage::class, mappedBy: 'work', cascade: ['persist', 'remove'], fetch: 'EAGER', orphanRemoval: true)]
    #[ORM\OrderBy(['position' => SortDirection::Ascending])]
    public Collection $images;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function image(string $id): ?ServiceWorkImage
    {
        return $this->images->findFirst(static fn (int $key, ServiceWorkImage $image): bool => $image->id === $id);
    }

    /** @return list<ServiceWorkImage> by position: the mapping's order is not applied to a collection already loaded */
    public function orderedImages(): array
    {
        $images = $this->images->toArray();
        usort($images, static fn (ServiceWorkImage $a, ServiceWorkImage $b): int => $a->position <=> $b->position);

        return $images;
    }
}
