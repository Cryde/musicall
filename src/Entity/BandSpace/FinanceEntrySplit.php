<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\FinanceEntrySplitRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: FinanceEntrySplitRepository::class)]
#[ORM\Table(name: 'finance_entry_split')]
class FinanceEntrySplit
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

    #[ORM\ManyToOne(targetEntity: FinanceEntry::class, inversedBy: 'splits')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public FinanceEntry $entry;

    #[ORM\ManyToOne(targetEntity: BandSpaceMembership::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?BandSpaceMembership $member = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $amount;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
