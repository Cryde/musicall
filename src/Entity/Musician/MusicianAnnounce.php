<?php

namespace App\Entity\Musician;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\State\Processor\Musician\MusicianAnnouncePostProcessor;
use App\State\Provider\Musician\MusicianAnnounceLastProvider;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MusicianAnnounceRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: 'musician_announces/last',
            openapi: new Operation(tags: ['Musician announce']),
            paginationEnabled: false,
            order: ['creationDatetime' => 'DESC'],
            normalizationContext: ['groups' => [MusicianAnnounce::LIST_LAST]],
            name: 'api_musician_announces_get_last_collection',
            provider: MusicianAnnounceLastProvider::class
        ),
        new GetCollection(
            uriTemplate: 'musician_announces/self',
            openapi: new Operation(tags: ['Musician announce']), // No need to paginate for now, user won't have tons of announce
            paginationEnabled: false,
            order: ['creationDatetime' => 'DESC'],
            normalizationContext: ['groups' => [MusicianAnnounce::ITEM_SELF]],
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_musician_announces_get_self_collection'
        ),
        new Post(
            openapi: new Operation(tags: ['Musician announce']),
            normalizationContext: ['groups' => [MusicianAnnounce::ITEM_SELF]],
            denormalizationContext: ['groups' => [MusicianAnnounce::POST]],
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_musician_announces_post',
            processor: MusicianAnnouncePostProcessor::class
        ),
        new Get(
            openapi: new Operation(tags: ['Musician announce']),
            normalizationContext: ['groups' => [MusicianAnnounce::ITEM]],
        ),
    ]
)]
class MusicianAnnounce
{
    const ITEM = 'MUSICIAN_ANNOUNCE_ITEM';
    const ITEM_SELF = 'MUSICIAN_ANNOUNCE_SELF';
    const LIST_LAST = 'MUSICIAN_ANNOUNCE_LIST_LAST';
    const POST = 'MUSICIAN_ANNOUNCE_POST';

    final const LIMIT_LAST_ANNOUNCES = 10;
    final const TYPE_MUSICIAN = 1;
    final const TYPE_BAND = 2;
    final const TYPES = [self::TYPE_MUSICIAN, self::TYPE_BAND];

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([MusicianAnnounce::ITEM_SELF])]
    private $id;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([MusicianAnnounce::ITEM_SELF])]
    private $creationDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([MusicianAnnounce::LIST_LAST])]
    private User $author;

    #[Assert\Choice(choices: MusicianAnnounce::TYPES)]
    #[ORM\Column(type: Types::SMALLINT)]
    #[Groups([MusicianAnnounce::ITEM_SELF, MusicianAnnounce::POST, MusicianAnnounce::LIST_LAST])]
    private $type;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([MusicianAnnounce::ITEM_SELF, MusicianAnnounce::POST, MusicianAnnounce::LIST_LAST])]
    private Instrument $instrument;

    /**
     * @var Collection<int, Style>
     */
    #[Assert\Length(min: 1)]
    #[ORM\ManyToMany(targetEntity: Style::class)]
    #[Groups([MusicianAnnounce::ITEM_SELF, MusicianAnnounce::POST, MusicianAnnounce::LIST_LAST])]
    private Collection $styles;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([MusicianAnnounce::ITEM_SELF, MusicianAnnounce::POST, MusicianAnnounce::LIST_LAST])]
    private string $locationName;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(MusicianAnnounce::POST)]
    private string $longitude;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(MusicianAnnounce::POST)]
    private string $latitude;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([MusicianAnnounce::ITEM_SELF, MusicianAnnounce::POST])]
    private ?string $note = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->styles = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): self
    {
        $this->author = $author;

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

    public function getInstrument(): ?Instrument
    {
        return $this->instrument;
    }

    public function setInstrument(?Instrument $instrument): self
    {
        $this->instrument = $instrument;

        return $this;
    }

    public function getStyles(): Collection
    {
        return $this->styles;
    }

    public function addStyle(Style $style): self
    {
        if (!$this->styles->contains($style)) {
            $this->styles[] = $style;
        }

        return $this;
    }

    public function removeStyle(Style $style): self
    {
        if ($this->styles->contains($style)) {
            $this->styles->removeElement($style);
        }

        return $this;
    }

    public function getLocationName(): ?string
    {
        return $this->locationName;
    }

    public function setLocationName(string $locationName): self
    {
        $this->locationName = $locationName;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(string $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(string $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }
}
