<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\Image\PublicationFeaturedImage;
use App\Repository\PublicationFeaturedRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PublicationFeaturedRepository::class)]
#[ApiResource(
    collectionOperations: ['get' => ['normalization_context' => ['groups' => PublicationFeatured::LIST]]],
    itemOperations: ['get' => ['normalization_context' => ['groups' => PublicationFeatured::ITEM]]]
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => SearchFilterInterface::STRATEGY_EXACT])]
class PublicationFeatured
{
    final const LIST = 'PUBLICATION_SUBCATEGORY_LIST';
    final const ITEM = 'PUBLICATION_SUBCATEGORY_ITEM';

    final const STATUS_DRAFT = 0;
    final const STATUS_ONLINE = 1;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private $id;

    #[Assert\NotBlank(message: 'Vous devez fournir un titre', groups: ['add', 'edit', 'publish'])]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([PublicationFeatured::LIST, PublicationFeatured::ITEM])]
    private $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([PublicationFeatured::LIST, PublicationFeatured::ITEM])]
    private $description;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $creationDatetime;

    #[Assert\NotBlank(groups: ['add', 'edit', 'publish'])]
    #[ORM\Column(type: Types::SMALLINT)]
    #[Groups([PublicationFeatured::LIST, PublicationFeatured::ITEM])]
    private $level;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $status = self::STATUS_DRAFT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private $publicationDatetime;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([PublicationFeatured::LIST, PublicationFeatured::ITEM])]
    private $publication;

    #[Assert\NotNull(message: 'Vous devez spécifier une image de cover', groups: ['publish'])]
    #[ORM\OneToOne(targetEntity: PublicationFeaturedImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups([PublicationFeatured::LIST, PublicationFeatured::ITEM])]
    private $cover;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    #[Groups([PublicationFeatured::LIST, PublicationFeatured::ITEM])]
    private ?array $options = ['color' => 'dark'];

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCreationDatetime(): ?\DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(\DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(?int $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPublicationDatetime(): ?\DateTimeInterface
    {
        return $this->publicationDatetime;
    }

    public function setPublicationDatetime(?\DateTimeInterface $publicationDatetime): self
    {
        $this->publicationDatetime = $publicationDatetime;

        return $this;
    }

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(?Publication $publication): self
    {
        $this->publication = $publication;

        return $this;
    }

    public function getCover(): ?PublicationFeaturedImage
    {
        return $this->cover;
    }

    public function setCover(?PublicationFeaturedImage $cover): self
    {
        $this->cover = $cover;

        return $this;
    }


    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): self
    {
        $this->options = $options;

        return $this;
    }
}
