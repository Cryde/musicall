<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Contracts\Metric\ViewableInterface;
use App\Contracts\SluggableEntityInterface;
use App\Entity\Comment\CommentThread;
use App\Entity\Image\PublicationCover;
use App\Entity\Image\PublicationImage;
use App\Entity\Metric\ViewCache;
use App\Repository\PublicationRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PublicationRepository::class)]
#[ORM\Index(columns: ['title', 'short_description', 'content'], flags: ['fulltext'])]
#[ApiResource(
    operations: [
        new GetCollection(
            openapi: new Operation(tags: ['Publications']),
            // "PublicationOnlineExtension" add automatic filter on status of the publication
            paginationItemsPerPage: Publication::LIST_ITEMS_PER_PAGE,
            normalizationContext: ['groups' => [Publication::LIST], 'skip_null_values' => false],
            name: 'api_publication_get_collection'
        ),
    ]
)]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['publicationDatetime' => OrderFilterInterface::DIRECTION_DESC])]
#[ApiFilter(filterClass: SearchFilter::class, properties: [
    'subCategory.slug' => SearchFilterInterface::STRATEGY_EXACT,
    'subCategory.type' => SearchFilterInterface::STRATEGY_EXACT
])]
class Publication implements ViewableInterface, SluggableEntityInterface
{
    final const LIST_ITEMS_PER_PAGE = 12;
    final const ITEM = 'PUBLICATION_ITEM';
    final const LIST = 'PUBLICATION_LIST';

    final const TYPE_TEXT = 1;
    final const TYPE_VIDEO = 2;
    final const TYPE_VIDEO_LABEL = 'video';
    final const TYPE_TEXT_LABEL = 'text';

    final const STATUS_DRAFT = 0;
    final const STATUS_ONLINE = 1;
    final const STATUS_PENDING = 2;

    final const ALL_STATUS = [self::STATUS_ONLINE, self::STATUS_DRAFT, self::STATUS_PENDING];

    final const STATUS_LABEL = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_ONLINE => 'Publié',
        self::STATUS_PENDING => 'En validation',
    ];

    final const STATUS_DRAFT_STR = '0';
    final const STATUS_ONLINE_STR = '1';
    final const STATUS_PENDING_STR = '2';

    final const ALL_STATUS_STR = [self::STATUS_DRAFT_STR, self::STATUS_ONLINE_STR, self::STATUS_PENDING_STR];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ApiProperty(identifier: false)]
    #[Groups([Publication::LIST])]
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Le titre ne peut être vide')]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([Publication::ITEM, Publication::LIST])]
    private string $title;

    #[Assert\NotBlank(message: 'La catégorie ne peut être vide')]
    #[ORM\ManyToOne(targetEntity: PublicationSubCategory::class, inversedBy: "publications")]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([Publication::ITEM, Publication::LIST])]
    private PublicationSubCategory $subCategory;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "publications")]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([Publication::ITEM, Publication::LIST])]
    private User $author;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups([Publication::ITEM, Publication::LIST])]
    private string $slug;

    #[Assert\NotBlank(message: 'La description de la publication ne doit pas être vide', groups: ['publication'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([Publication::ITEM])]
    private ?string $shortDescription = null;

    #[Assert\NotBlank(message: 'Il doit y avoir du contenu dans la publication', groups: ['publication'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([Publication::ITEM])]
    private ?string $content = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $editionDatetime = null;

    #[Assert\Type(type: DateTime::class, groups: ['publication'])]
    #[Assert\NotBlank(groups: ['publication'])]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups([Publication::ITEM, Publication::LIST])]
    private ?DateTimeInterface $publicationDatetime = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $status = self::STATUS_DRAFT;

    /**
     * @var Collection<int, PublicationImage>
     */
    #[ORM\OneToMany(mappedBy: "publication", targetEntity: PublicationImage::class)]
    private Collection $images;

    #[Assert\NotNull(message: 'Vous devez ajouter une image de cover', groups: ['publication'])]
    #[ORM\OneToOne(targetEntity: PublicationCover::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups([Publication::LIST])]
    private ?PublicationCover $cover = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $type = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $oldPublicationId = null;

    #[ORM\ManyToOne(targetEntity: CommentThread::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups([Publication::ITEM])]
    private ?CommentThread $thread = null;

    #[ORM\OneToOne(targetEntity: ViewCache::class, cascade: ['persist', 'remove'])]
    private ?ViewCache $viewCache = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->images = new ArrayCollection();
    }

    #[Groups([Publication::ITEM, Publication::LIST])]
    public function getTypeLabel(): string
    {
        return $this->type === Publication::TYPE_VIDEO ? Publication::TYPE_VIDEO_LABEL : Publication::TYPE_TEXT_LABEL;
    }

    #[Groups([Publication::LIST])]
    public function getDescription(): ?string
    {
        return $this->type === Publication::TYPE_TEXT ? $this->shortDescription : null;
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

    public function getCreationDatetime(): DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getEditionDatetime(): ?DateTimeInterface
    {
        return $this->editionDatetime;
    }

    public function setEditionDatetime(DateTimeInterface $editionDatetime): self
    {
        $this->editionDatetime = $editionDatetime;

        return $this;
    }

    public function getPublicationDatetime(): ?DateTimeInterface
    {
        return $this->publicationDatetime;
    }

    public function setPublicationDatetime(?DateTimeInterface $publicationDatetime): self
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

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getSubCategory(): PublicationSubCategory
    {
        return $this->subCategory;
    }

    public function setSubCategory(PublicationSubCategory $subCategory): self
    {
        $this->subCategory = $subCategory;

        return $this;
    }

    /**
     * @return Collection<int, PublicationImage>
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

    public function getThread(): ?CommentThread
    {
        return $this->thread;
    }

    public function setThread(?CommentThread $thread): self
    {
        $this->thread = $thread;

        return $this;
    }

    public function getViewCache(): ?ViewCache
    {
        return $this->viewCache;
    }

    public function setViewCache(?ViewCache $viewCache): self
    {
        $this->viewCache = $viewCache;

        return $this;
    }

    public function getViewableId(): ?string
    {
        return $this->id !== null ? (string) $this->id : null;
    }

    public function getViewableType(): string
    {
        return 'app_publication';
    }
}
