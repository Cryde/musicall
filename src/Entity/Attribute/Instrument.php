<?php

declare(strict_types=1);

namespace App\Entity\Attribute;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Contracts\SluggableEntityInterface;
use App\Repository\Attribute\InstrumentRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Ignore;
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
        order: ['musicianName' => 'ASC'],
        name: 'api_instruments_get_collection',
    ),
])]
class Instrument implements SluggableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    public string $name;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    public string $musicianName;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    public string $slug;

    #[Ignore]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
