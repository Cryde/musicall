<?php

namespace App\Entity;

use App\Entity\Image\PublicationCover;
use App\Entity\Image\PublicationImage;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PublicationRepository")
 */
class Publication
{
    const CATEGORY_PUBLICATION = 1;
    const CATEGORY_COURSE = 2;

    const TYPE_TEXT = 1;
    const TYPE_VIDEO = 2;
    const TYPE_VIDEO_LABEL = 'video';
    const TYPE_TEXT_LABEL = 'text';

    const STATUS_DRAFT = 0;
    const STATUS_ONLINE = 1;
    const STATUS_PENDING = 2;

    const STATUS_LABEL = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_ONLINE => 'Publié',
        self::STATUS_PENDING => 'En validation',
    ];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;
    /**
     * @Assert\NotBlank(message="Le titre ne peut être vide")
     *
     * @ORM\Column(type="string", length=255)
     */
    private $title;
    /**
     * @ORM\Column(type="smallint")
     */
    private $category;
    /**
     * @Assert\NotBlank(message="La catégorie ne peut être vide")
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\PublicationSubCategory", inversedBy="publications")
     * @ORM\JoinColumn(nullable=false)
     */
    private $subCategory;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="publications")
     * @ORM\JoinColumn(nullable=false)
     */
    private $author;
    /**
     * @Gedmo\Slug(fields={"title"})
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $slug;
    /**
     * @Assert\NotBlank(groups={"publication"}, message="La description de la publication ne doit pas être vide")
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $shortDescription;

    /**
     * @Assert\NotBlank(groups={"publication"}, message="Il doit y avoir du contenu dans la publication")
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $content;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDatetime;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $editionDatetime;

    /**
     * @Assert\DateTime(groups={"publication"})
     * @Assert\NotBlank(groups={"publication"})
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $publicationDatetime;
    /**
     * @ORM\Column(type="smallint")
     */
    private $status;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Image\PublicationImage", mappedBy="publication")
     */
    private $images;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Image\PublicationCover", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $cover;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $type;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $oldPublicationId;
    
    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
        $this->status = self::STATUS_DRAFT;
        $this->images = new ArrayCollection();
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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

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

    public function getEditionDatetime(): ?\DateTimeInterface
    {
        return $this->editionDatetime;
    }

    public function setEditionDatetime(\DateTimeInterface $editionDatetime): self
    {
        $this->editionDatetime = $editionDatetime;

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getSubCategory(): ?PublicationSubCategory
    {
        return $this->subCategory;
    }

    public function setSubCategory(?PublicationSubCategory $subCategory): self
    {
        $this->subCategory = $subCategory;

        return $this;
    }

    public function getCategory(): ?int
    {
        return $this->category;
    }

    public function setCategory(int $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection|PublicationImage[]
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(PublicationImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images[] = $image;
        }

        return $this;
    }

    public function removeImage(PublicationImage $image): self
    {
        if ($this->images->contains($image)) {
            $this->images->removeElement($image);
        }

        return $this;
    }

    public function getCover(): ?PublicationCover
    {
        return $this->cover;
    }

    public function setCover(?PublicationCover $cover): self
    {
        $this->cover = $cover;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getOldPublicationId(): ?int
    {
        return $this->oldPublicationId;
    }

    public function setOldPublicationId(?int $oldPublicationId): self
    {
        $this->oldPublicationId = $oldPublicationId;

        return $this;
    }
}
