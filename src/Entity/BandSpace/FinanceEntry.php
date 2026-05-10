<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Repository\BandSpace\FinanceEntryRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: FinanceEntryRepository::class)]
#[ORM\Table(name: 'finance_entry')]
#[ORM\Index(name: 'IDX_finance_entry_category_status', columns: ['category_id', 'status'])]
class FinanceEntry
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: FinanceCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public FinanceCategory $category;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $label;

    #[ORM\Column(type: Types::STRING, nullable: false, enumType: FinanceEntryType::class)]
    public FinanceEntryType $type;

    #[ORM\Column(type: Types::STRING, nullable: false, enumType: FinanceEntryStatus::class)]
    public FinanceEntryStatus $status;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $amount = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $amountMin = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $amountMax = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    public DateTimeInterface $date;

    #[ORM\Column(type: Types::STRING, nullable: false, enumType: FinanceEntryScope::class)]
    public FinanceEntryScope $scope;

    #[ORM\ManyToOne(targetEntity: BandSpaceMembership::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?BandSpaceMembership $member = null;

    #[ORM\ManyToOne(targetEntity: FinanceRecurrence::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?FinanceRecurrence $recurrence = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    /** @var Collection<int, FinanceEntrySplit> */
    #[ORM\OneToMany(targetEntity: FinanceEntrySplit::class, mappedBy: 'entry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $splits;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->splits = new ArrayCollection();
    }
}
