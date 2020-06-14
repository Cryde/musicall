<?php

namespace App\Entity;

use App\Entity\Image\PublicationFeaturedImage;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PublicationFeaturedRepository")
 */
class PublicationFeatured
{
    const STATUS_DRAFT = 0;
    const STATUS_ONLINE = 1;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(groups={"add", "edit", "publish"}, message="Vous devez fournir un titre")
     */
    private $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="datetime")
     */
    private \DateTimeInterface $creationDatetime;

    /**
     * @ORM\Column(type="smallint")
     * @Assert\NotBlank(groups={"add", "edit", "publish"})
     */
    private $level;

    /**
     * @ORM\Column(type="smallint")
     */
    private $status;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $publicationDatetime;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Publication")
     * @ORM\JoinColumn(nullable=false)
     */
    private $publication;

    /**
     * @Assert\NotNull(groups={"publish"}, message="Vous devez spécifier une image de cover")
     * @ORM\OneToOne(targetEntity="App\Entity\Image\PublicationFeaturedImage", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $cover;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $options = [];

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
        $this->status = self::STATUS_DRAFT;
        $this->options = ['color' => 'dark'];
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
