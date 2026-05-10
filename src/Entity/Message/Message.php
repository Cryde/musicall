<?php

declare(strict_types=1);

namespace App\Entity\Message;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * `#[ApiResource(operations: [])]` keeps the entity registered for IRI generation
 * in nested entity contexts (e.g. `MessageResource->thread.last_message` rendering)
 * and for IRI-based denormalization when API requests reference messages by URL.
 * No HTTP routes are exposed — the API surface lives on `MessageResource`.
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ApiResource(operations: [])]
class Message
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

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $author;

    #[ORM\ManyToOne(targetEntity: MessageThread::class, inversedBy: "messages")]
    #[ORM\JoinColumn(nullable: false)]
    public MessageThread $thread;

    #[ORM\Column(type: Types::TEXT)]
    public string $content;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
