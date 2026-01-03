<?php

declare(strict_types=1);

namespace App\Entity\Forum;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\User;
use App\Repository\Forum\ForumPostRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ForumPostRepository::class)]
class ForumPost
{
    final public const LIST = 'FORUM_POST_LIST';

    final public const MIN_MESSAGE_LENGTH = 10;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private $id;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updateDatetime = null;
    #[Assert\NotBlank]
    #[Assert\Length(min: ForumPost::MIN_MESSAGE_LENGTH)]
    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[Assert\NotBlank]
    #[ORM\ManyToOne(targetEntity: ForumTopic::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ForumTopic $topic;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(genId: false)]
    private User $creator;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id ? (string) $this->id : null;
    }

    public function getCreationDatetime(): DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getUpdateDatetime(): ?DateTimeInterface
    {
        return $this->updateDatetime;
    }

    public function setUpdateDatetime(?DateTimeInterface $updateDatetime): self
    {
        $this->updateDatetime = $updateDatetime;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getTopic(): ?ForumTopic
    {
        return $this->topic;
    }

    public function setTopic(?ForumTopic $topic): self
    {
        $this->topic = $topic;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): self
    {
        $this->creator = $creator;

        return $this;
    }
}
