<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Enum\BandSpace\TaskPriority;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\TaskRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
#[ORM\Index(columns: ['status'], name: 'idx_task_status')]
#[ORM\Index(columns: ['priority'], name: 'idx_task_priority')]
#[ORM\Index(columns: ['archive_datetime'], name: 'idx_task_archive_datetime')]
class Task
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

    #[ORM\ManyToOne(targetEntity: BandSpace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpace $bandSpace;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: false, enumType: TaskStatus::class)]
    public TaskStatus $status = TaskStatus::Todo;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: false, enumType: TaskPriority::class)]
    public TaskPriority $priority = TaskPriority::Normal;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $dueDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $createdBy = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'task_assignee')]
    public Collection $assignees;

    #[ORM\ManyToOne(targetEntity: TaskCategory::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?TaskCategory $category = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $archiveDatetime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $completedDatetime = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $position = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->assignees = new ArrayCollection();
    }
}
