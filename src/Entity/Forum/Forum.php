<?php declare(strict_types=1);

namespace App\Entity\Forum;

use App\Contracts\SluggableEntityInterface;
use App\Repository\Forum\ForumRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
class Forum implements SluggableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    public string $slug;

    #[ORM\Column(type: Types::TEXT)]
    public string $description;

    #[ORM\ManyToOne(targetEntity: ForumCategory::class, inversedBy: 'forums')]
    #[ORM\JoinColumn(nullable: false)]
    public ForumCategory $forumCategory;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $position;

    #[ORM\Column(type: Types::INTEGER)]
    public int $topicNumber;

    #[ORM\Column(type: Types::INTEGER)]
    public int $postNumber;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
