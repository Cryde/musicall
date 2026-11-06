<?php

declare(strict_types=1);

namespace App\Entity\Profile;

use App\Entity\User;
use App\Enum\Musician\MediaPlatform;
use App\Repository\Profile\ProfilePostRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SortDirection;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/** A post a member writes on their own profile wall (#1316): a text, up to 4 photos or one media link. */
#[ORM\Entity(repositoryClass: ProfilePostRepository::class)]
#[ORM\Table(name: 'profile_post')]
#[ORM\Index(name: 'profile_post_wall_idx', columns: ['author_id', 'creation_datetime', 'id'])]
class ProfilePost
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $author;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $content = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: MediaPlatform::class)]
    public ?MediaPlatform $mediaPlatform = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    public ?string $mediaUrl = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $mediaEmbedId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $mediaTitle = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $isPinned = false;

    /**
     * Removed through the ORM with the post, so VichUploader deletes each stored photo.
     *
     * @var Collection<int, ProfilePostImage>
     */
    #[ORM\OneToMany(targetEntity: ProfilePostImage::class, mappedBy: 'post', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => SortDirection::Ascending])]
    public Collection $images;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    /** Set only when the text changes: it is what « modifié » reads. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updateDatetime = null;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function addImage(ProfilePostImage $image): void
    {
        $image->post = $this;
        $image->position = $this->images->count();
        $this->images->add($image);
    }
}
