<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Entity\User;
use App\Repository\User\UserNotificationPreferenceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: UserNotificationPreferenceRepository::class)]
class UserNotificationPreference
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'notificationPreference')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $siteNews = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $weeklyRecap = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $messageReceived = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $publicationComment = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $forumReply = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $marketing = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $activityReminder = true;

    /** Seen « en ligne » by the members of one's bands while their chat is open (#1040). */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $showOnlinePresence = true;

    // Push categories (#1110), all on by default: a token only exists once the phone allowed notifications.
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $pushMessageReceived = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $pushPublicationComment = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $pushForumReply = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $pushModeration = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $pushBandChat = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $pushBandMention = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $pushBandTasks = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $pushBandAgenda = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $pushBandFinance = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $pushBandMembership = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}
