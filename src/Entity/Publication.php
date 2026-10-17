<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Contracts\Metric\VotableInterface;
use App\Contracts\Metric\ViewableInterface;
use App\Contracts\SluggableEntityInterface;
use App\Entity\Comment\CommentThread;
use App\Entity\Image\PublicationCover;
use App\Entity\Image\PublicationImage;
use App\Entity\Metric\ViewCache;
use App\Entity\Metric\VoteCache;
use App\Entity\Publication\Tag;
use App\Repository\PublicationRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PublicationRepository::class)]
#[ORM\Index(columns: ['title', 'short_description', 'content'], flags: ['fulltext'])]
#[ORM\UniqueConstraint(name: 'publication_translation_group_language_unique', columns: ['translation_group', 'language'])]
#[ApiResource(operations: [])]
class Publication implements ViewableInterface, VotableInterface, SluggableEntityInterface
{
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

    final const STATUS_DRAFT_STR = '0';
    final const STATUS_ONLINE_STR = '1';
    final const STATUS_PENDING_STR = '2';

    final const ALL_STATUS_STR = [self::STATUS_DRAFT_STR, self::STATUS_ONLINE_STR, self::STATUS_PENDING_STR];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ApiProperty(identifier: false)]
    #[Groups([Publication::LIST])]
    public ?int $id = null;

    #[Assert\NotBlank(message: 'publication.title_required')]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([Publication::ITEM, Publication::LIST])]
    public string $title;

    #[Assert\NotBlank(message: 'publication.category_required')]
    #[ORM\ManyToOne(targetEntity: PublicationSubCategory::class, inversedBy: "publications")]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([Publication::ITEM, Publication::LIST])]
    public PublicationSubCategory $subCategory;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "publications")]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([Publication::ITEM, Publication::LIST])]
    public User $author;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups([Publication::ITEM, Publication::LIST])]
    public string $slug;

    #[Assert\NotBlank(message: 'publication.description_required', groups: ['publication'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([Publication::ITEM])]
    public ?string $shortDescription = null;

    #[Assert\NotBlank(message: 'publication.content_required', groups: ['publication'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([Publication::ITEM])]
    public ?string $content = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $editionDatetime = null;

    #[Assert\Type(type: DateTime::class, groups: ['publication'])]
    #[Assert\NotBlank(groups: ['publication'])]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups([Publication::ITEM, Publication::LIST])]
    public ?DateTimeInterface $publicationDatetime = null;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $status = self::STATUS_DRAFT;

    /**
     * @var Collection<int, PublicationImage>
     */
    #[ORM\OneToMany(targetEntity: PublicationImage::class, mappedBy: "publication")]
    public Collection $images;

    #[Assert\NotNull(message: 'publication.cover_required', groups: ['publication'])]
    #[ORM\OneToOne(targetEntity: PublicationCover::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups([Publication::LIST])]
    public ?PublicationCover $cover = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    public ?int $type = null;

    /** Null on a video: it is shown in every language and never translated. */
    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    public ?string $language = null;

    /** Shared by the translations of one text, each its own row. Null when it has none. */
    #[ORM\Column(type: Types::GUID, nullable: true)]
    public ?string $translationGroup = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $oldPublicationId = null;

    #[ORM\ManyToOne(targetEntity: CommentThread::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups([Publication::ITEM])]
    public ?CommentThread $thread = null;

    #[ORM\OneToOne(targetEntity: ViewCache::class, cascade: ['persist', 'remove'])]
    public ?ViewCache $viewCache = null;

    #[ORM\OneToOne(targetEntity: VoteCache::class, cascade: ['persist', 'remove'])]
    public ?VoteCache $voteCache = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'map_publication_tag')]
    public Collection $tags;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->images = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    /**
     * Sets the type and its language together, so a text always has one and a video never does.
     * A text starts in its author's language, so set the author first.
     */
    public function assignType(int $type): void
    {
        $this->type = $type;
        $this->language = $type === self::TYPE_VIDEO ? null : $this->author->locale;
    }

    /**
     * Makes this publication a translation of $sibling, opening a group on $sibling if it has none.
     * A video is never translated: it is already shown in every language.
     */
    public function joinTranslationGroupOf(Publication $sibling): void
    {
        if ($this->type === self::TYPE_VIDEO || $sibling->type === self::TYPE_VIDEO) {
            throw new \LogicException('A video publication cannot join a translation group.');
        }
        if ($sibling === $this) {
            throw new \LogicException('A publication cannot be a translation of itself.');
        }
        if ($this->translationGroup !== null) {
            throw new \LogicException('A publication already in a translation group cannot join another one.');
        }
        // Only the sibling is checked here: the unique index is the backstop for a third member.
        if ($sibling->language === $this->language) {
            throw new \LogicException('A translation group holds one publication per language.');
        }

        $sibling->translationGroup ??= Uuid::v7()->toRfc4122();
        $this->translationGroup = $sibling->translationGroup;
    }

    #[Groups([Publication::LIST])]
    public function getDescription(): ?string
    {
        return $this->type === Publication::TYPE_TEXT ? $this->shortDescription : null;
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

    public function getViewableId(): ?string
    {
        return $this->id !== null ? (string) $this->id : null;
    }

    public function getViewableType(): string
    {
        return 'app_publication';
    }

    public function getVotableId(): ?string
    {
        return $this->id !== null ? (string) $this->id : null;
    }

    public function getVotableType(): string
    {
        return 'app_publication';
    }
}
