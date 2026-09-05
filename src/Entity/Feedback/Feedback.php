<?php declare(strict_types=1);

namespace App\Entity\Feedback;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Enum\Feedback\FeedbackModule;
use App\Enum\Feedback\FeedbackStatus;
use App\Enum\Feedback\FeedbackType;
use App\Repository\Feedback\FeedbackRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: FeedbackRepository::class)]
#[ORM\Table(name: 'feedback')]
#[ORM\Index(name: 'idx_feedback_triage', columns: ['status', 'creation_datetime'])]
class Feedback
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

    #[ORM\Column(type: Types::STRING, length: 20, enumType: FeedbackType::class)]
    public FeedbackType $type;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: FeedbackModule::class)]
    public FeedbackModule $module;

    #[ORM\Column(type: Types::TEXT)]
    public string $message;

    /**
     * Optional even for an anonymous sender. A report carrying a module and a page is actionable
     * without one, and requiring it would cost us the drive by report that is the whole point.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $email = null;

    /**
     * Set from the security token, never from the request body. Accounts are anonymized rather than
     * removed (see DeleteAccountProcedure), so this never dangles; SET NULL is belt and braces.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $user = null;

    /**
     * Survives the purge of the space it came from, because the report is still worth reading once
     * the space is gone.
     */
    #[ORM\ManyToOne(targetEntity: BandSpace::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?BandSpace $bandSpace = null;

    /**
     * A path, never an absolute URL. An admin clicks these from the triage table, so accepting an
     * absolute URL would turn the admin list into an open redirect with a human in the loop.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $pageUrl;

    /** Read from the request, never from the body. Truncated to fit rather than rejected. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $userAgent = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: FeedbackStatus::class)]
    public FeedbackStatus $status = FeedbackStatus::New;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
