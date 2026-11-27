<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Contracts\Metric\ViewableInterface;
use App\Entity\Attribute\Style;
use App\Entity\Metric\ViewCache;
use App\Entity\User;
use App\Enum\Service\BeforeAfterSide;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServicePlaceType;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServicePart;
use App\Enum\Service\ServiceStatus;
use App\Repository\Service\ServiceRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SortDirection;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * What a member offers, one per kind (#683, #1318). What every kind has is a column or a table; what one kind
 * alone shows is in `details`, read through that kind's class (TeachingDetails). No inverse side on User: each
 * inverse OneToOne is loaded with every User (#730), so readers ask ServiceRepository.
 */
#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'service')]
#[ORM\UniqueConstraint(name: 'unique_service_user_kind', columns: ['user_id', 'kind'])]
class Service implements ViewableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $user;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ServiceKind::class)]
    public ServiceKind $kind;

    /** A draft by default: only the teacher profile endpoint the app calls creates a published one (#1406). */
    #[ORM\Column(type: Types::STRING, length: 20, enumType: ServiceStatus::class)]
    public ServiceStatus $status = ServiceStatus::Draft;

    /** Set once, by publish(): the date its wall line carries, so going offline and back never repeats it (#1415). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $firstPublicationDatetime = null;

    /** Its place among its member's services, which they set in « Mes services » and their profile follows (#1415). */
    #[ORM\Column(type: Types::SMALLINT)]
    public int $position = 0;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $headline = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $yearsOfExperience = null;

    /**
     * The kind's main search filter, read with JSON_CONTAINS.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $specialities = [];

    /**
     * What only this kind shows, in the shape of its details class.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $details = [];

    /**
     * @var Collection<int, ServiceInstrument>
     */
    #[ORM\OneToMany(targetEntity: ServiceInstrument::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $instruments;

    /**
     * @var Collection<int, Style>
     */
    #[ORM\ManyToMany(targetEntity: Style::class)]
    #[ORM\JoinTable(name: 'service_style')]
    public Collection $styles;

    /**
     * @var Collection<int, ServiceMedia>
     */
    #[ORM\OneToMany(targetEntity: ServiceMedia::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => SortDirection::Ascending])]
    public Collection $media;

    /**
     * @var Collection<int, ServiceOffer>
     */
    #[ORM\OneToMany(targetEntity: ServiceOffer::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $offers;

    /**
     * @var Collection<int, ServiceAvailability>
     */
    #[ORM\OneToMany(targetEntity: ServiceAvailability::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $availability;

    /**
     * @var Collection<int, ServicePlace>
     */
    #[ORM\OneToMany(targetEntity: ServicePlace::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $places;

    /**
     * @var Collection<int, ServiceLink>
     */
    #[ORM\OneToMany(targetEntity: ServiceLink::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $links;

    /**
     * « Avant, après » (#1410): one sound per side at most.
     *
     * @var Collection<int, ServiceAudio>
     */
    #[ORM\OneToMany(targetEntity: ServiceAudio::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $audio;

    /**
     * « Réalisations » (#1407), for a kind that has them.
     *
     * @var Collection<int, ServiceWork>
     */
    #[ORM\OneToMany(targetEntity: ServiceWork::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => SortDirection::Ascending])]
    public Collection $works;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updateDatetime = null;

    #[ORM\OneToOne(targetEntity: ViewCache::class, cascade: ['persist', 'remove'])]
    public ?ViewCache $viewCache = null;

    public function __construct()
    {
        $this->instruments = new ArrayCollection();
        $this->styles = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->offers = new ArrayCollection();
        $this->availability = new ArrayCollection();
        $this->places = new ArrayCollection();
        $this->links = new ArrayCollection();
        $this->audio = new ArrayCollection();
        $this->works = new ArrayCollection();
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function addInstrument(ServiceInstrument $instrument): self
    {
        if (!$this->instruments->contains($instrument)) {
            $this->instruments->add($instrument);
            $instrument->service = $this;
        }

        return $this;
    }

    public function removeInstrument(ServiceInstrument $instrument): self
    {
        $this->instruments->removeElement($instrument);

        return $this;
    }

    public function addStyle(Style $style): self
    {
        if (!$this->styles->contains($style)) {
            $this->styles->add($style);
        }

        return $this;
    }

    public function removeStyle(Style $style): self
    {
        $this->styles->removeElement($style);

        return $this;
    }

    public function addOffer(ServiceOffer $offer): self
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->service = $this;
        }

        return $this;
    }

    public function removeOffer(ServiceOffer $offer): self
    {
        $this->offers->removeElement($offer);

        return $this;
    }

    /** @return list<ServiceOffer> */
    public function offersIn(OfferUnit $unit): array
    {
        return array_values($this->offers->filter(static fn (ServiceOffer $offer): bool => $offer->unit === $unit)->toArray());
    }

    /** @return list<ServiceOffer> the cheapest first, as « Formules » lists them (#1410) */
    public function offersByPrice(): array
    {
        $offers = array_values($this->offers->toArray());
        usort($offers, static fn (ServiceOffer $a, ServiceOffer $b): int => [$a->price, (string) $a->title] <=> [$b->price, (string) $b->title]);

        return $offers;
    }

    /** The cheapest offer in the unit, or the cheapest of any unit without one: « 80 € le titre mixé » (#1410). */
    public function headlineOffer(?OfferUnit $unit): ?ServiceOffer
    {
        return ($unit instanceof OfferUnit ? $this->cheapestIn($unit) : null) ?? $this->offersByPrice()[0] ?? null;
    }

    /** The price its page and card lead with, in the unit its kind leads with. */
    public function leadOffer(): ?ServiceOffer
    {
        return $this->headlineOffer($this->kind->leadOfferUnit());
    }

    /** The cheapest offer in the unit, as entered: a package is never compared with a lesson. */
    public function cheapestIn(OfferUnit $unit): ?ServiceOffer
    {
        $cheapest = null;
        foreach ($this->offersIn($unit) as $offer) {
            if (!$cheapest instanceof ServiceOffer || $offer->price < $cheapest->price) {
                $cheapest = $offer;
            }
        }

        return $cheapest;
    }

    public function addAvailability(ServiceAvailability $availability): self
    {
        if (!$this->availability->contains($availability)) {
            $this->availability->add($availability);
            $availability->service = $this;
        }

        return $this;
    }

    public function removeAvailability(ServiceAvailability $availability): self
    {
        $this->availability->removeElement($availability);

        return $this;
    }

    public function addPlace(ServicePlace $place): self
    {
        if (!$this->places->contains($place)) {
            $this->places->add($place);
            $place->service = $this;
        }

        return $this;
    }

    public function removePlace(ServicePlace $place): self
    {
        $this->places->removeElement($place);

        return $this;
    }

    public function addLink(ServiceLink $link): self
    {
        if (!$this->links->contains($link)) {
            $this->links->add($link);
            $link->service = $this;
        }

        return $this;
    }

    public function removeLink(ServiceLink $link): self
    {
        $this->links->removeElement($link);

        return $this;
    }

    public function audioOn(BeforeAfterSide $side): ?ServiceAudio
    {
        return $this->audio->findFirst(static fn (int $key, ServiceAudio $audio): bool => $audio->side === $side);
    }

    public function work(string $id): ?ServiceWork
    {
        return $this->works->findFirst(static fn (int $key, ServiceWork $work): bool => $work->id === $id);
    }

    /** @return list<ServiceWork> by position: the mapping's order is not applied to a collection already loaded */
    public function orderedWorks(): array
    {
        $works = $this->works->toArray();
        usort($works, static fn (ServiceWork $a, ServiceWork $b): int => $a->position <=> $b->position);

        return $works;
    }

    /** The city a page title names: the member's own place first, then any place, then the profile's city. */
    public function mainCity(): ?string
    {
        $places = $this->places->filter(static fn (ServicePlace $place): bool => (bool) $place->city);
        $own = $places->filter(static fn (ServicePlace $place): bool => $place->type === ServicePlaceType::AtMyPlace)->first();
        $any = $own ?: $places->first();

        return $any ? $any->city : ($this->user->profile->location ?: null);
    }

    /** Whether one of its places is remote work, which a search card says. */
    public function isOfferedRemotely(): bool
    {
        return $this->places->exists(static fn (int $key, ServicePlace $place): bool => $place->type === ServicePlaceType::Remote);
    }

    public function isPublished(): bool
    {
        return $this->status === ServiceStatus::Published;
    }

    public function publish(DateTimeImmutable $now): void
    {
        $this->status = ServiceStatus::Published;
        $this->firstPublicationDatetime ??= $now;
    }

    /** @return list<ServicePart> what its kind needs before it can be published and it does not have yet */
    public function missingToPublish(): array
    {
        return array_values(array_filter($this->kind->requiredToPublish(), fn (ServicePart $part): bool => !$part->isFilledIn($this)));
    }

    /** The share of its kind's parts filled in, from 0 to 100. */
    public function completeness(): int
    {
        $parts = $this->kind->parts();
        $filled = array_filter($parts, fn (ServicePart $part): bool => $part->isFilledIn($this));

        return (int) round(count($filled) * 100 / count($parts));
    }

    public function getViewableId(): ?string
    {
        return $this->id;
    }

    public function getViewableType(): string
    {
        return $this->kind->viewableType();
    }
}
