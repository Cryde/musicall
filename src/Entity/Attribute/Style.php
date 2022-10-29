<?php

namespace App\Entity\Attribute;

use DateTimeInterface;
use DateTime;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use App\Contracts\SluggableEntityInterface;
use App\Repository\Attribute\StyleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity('slug')]
#[UniqueEntity('name')]
#[ORM\Entity(repositoryClass: StyleRepository::class)]
#[ORM\Table(name: 'attribute_style')]
#[ApiResource(operations: [
    new Get(),
    new GetCollection(paginationItemsPerPage: 100, name: 'api_styles_get_collection')
])]
class Style implements SluggableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::GUID)]
    #[ORM\GeneratedValue(strategy: 'UUID')]
    private $id;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private $name;

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
}
