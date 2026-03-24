<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\RecurrenceInterval;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: FinanceRecurrenceRepository::class)]
#[ORM\Table(name: 'finance_recurrence')]
class FinanceRecurrence
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

    #[ORM\Column(type: Types::INTEGER)]
    public int $amount;

    #[ORM\Column(type: Types::STRING, nullable: false, enumType: FinanceEntryScope::class)]
    public FinanceEntryScope $scope;

    #[ORM\Column(name: '`interval`', type: Types::STRING, nullable: false, enumType: RecurrenceInterval::class)]
    public RecurrenceInterval $interval;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $startDate;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $endDate;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    /** @var Collection<int, FinanceEntry> */
    #[ORM\OneToMany(targetEntity: FinanceEntry::class, mappedBy: 'recurrence')]
    public Collection $entries;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->entries = new ArrayCollection();
    }
}
