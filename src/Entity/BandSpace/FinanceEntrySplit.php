<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\FinanceEntrySplitRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: FinanceEntrySplitRepository::class)]
#[ORM\Table(name: 'finance_entry_split')]
#[ORM\Index(name: 'IDX_finance_entry_split_entry_member', columns: ['entry_id', 'member_id'])]
class FinanceEntrySplit
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: FinanceEntry::class, inversedBy: 'splits')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public FinanceEntry $entry;

    #[ORM\ManyToOne(targetEntity: BandSpaceMembership::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?BandSpaceMembership $member = null;

    #[ORM\Column(type: Types::BIGINT)]
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
