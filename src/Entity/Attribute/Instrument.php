<?php

namespace App\Entity\Attribute;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Search\MusicianSearchResult;
use App\Contracts\SluggableEntityInterface;
use App\Entity\Musician\MusicianAnnounce;
use App\Repository\Attribute\InstrumentRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity('slug')]
#[UniqueEntity('name')]
#[ORM\Entity(repositoryClass: InstrumentRepository::class)]
#[ORM\Table(name: 'attribute_instrument')]
#[ApiResource(operations: [
    new Get(openapi: new Operation(tags: ['Attributes']),),
    new GetCollection(
        openapi: new Operation(tags: ['Attributes']),
        paginationItemsPerPage: 100,
        name: 'api_instruments_get_collection',
    ),
])]
class Instrument implements SluggableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private $id;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private $name;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Groups([MusicianAnnounce::ITEM_SELF, MusicianAnnounce::LIST_LAST])]
    private $musicianName;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private $slug;

    #[Ignore]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCreationDatetime(): ?DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getMusicianName(): ?string
    {
        return $this->musicianName;
    }

    public function setMusicianName(string $musicianName): self
    {
        $this->musicianName = $musicianName;

        return $this;
    }
}
