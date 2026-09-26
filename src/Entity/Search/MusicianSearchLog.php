<?php

declare(strict_types=1);

namespace App\Entity\Search;

use App\Entity\Attribute\Instrument;
use App\Enum\Search\AiSearchOutcome;
use App\Enum\Search\MusicianSearchKind;
use App\Repository\Search\MusicianSearchLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

/**
 * One musician search as it was asked (#1075), for the homepage's frequent searches and the admin.
 * No user and no IP: the visitor hash changes every day, so it counts people without following them.
 */
#[ORM\Entity(repositoryClass: MusicianSearchLogRepository::class)]
#[ORM\Table(name: 'musician_search_log')]
#[ORM\Index(name: 'idx_musician_search_log_kind_datetime', columns: ['kind', 'search_datetime'])]
#[ORM\Index(name: 'idx_musician_search_log_datetime', columns: ['search_datetime'])]
class MusicianSearchLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: MusicianSearchKind::class)]
    public MusicianSearchKind $kind;

    /** The announce type searched for, as the search takes it: 1 a band's announce, 2 a musician's. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    public ?int $type = null;

    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Instrument $instrument = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    public array $styleIds = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $locationName = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    public ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    public ?float $longitude = null;

    /** The words typed, for an AI search only. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $aiQuery = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, enumType: AiSearchOutcome::class)]
    public ?AiSearchOutcome $aiOutcome = null;

    /** How many announces the first page showed, for a filters search: zero is demand the site does not meet. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    public ?int $firstPageResultCount = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    public string $visitorHash;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $authenticated = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $searchDatetime;

    public function __construct(MusicianSearchKind $kind, string $visitorHash, DateTimeImmutable $searchDatetime)
    {
        $this->kind = $kind;
        $this->visitorHash = $visitorHash;
        $this->searchDatetime = $searchDatetime;
    }
}
