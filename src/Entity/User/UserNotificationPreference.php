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
    private ?string $id = null;

    #[ORM\OneToOne(inversedBy: 'notificationPreference', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $siteNews = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $weeklyRecap = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $messageReceived = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $publicationComment = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $forumReply = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $marketing = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $activityReminder = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function isSiteNews(): bool
    {
        return $this->siteNews;
    }

    public function setSiteNews(bool $siteNews): self
    {
        $this->siteNews = $siteNews;

        return $this;
    }

    public function isWeeklyRecap(): bool
    {
        return $this->weeklyRecap;
    }

    public function setWeeklyRecap(bool $weeklyRecap): self
    {
        $this->weeklyRecap = $weeklyRecap;

        return $this;
    }

    public function isMessageReceived(): bool
    {
        return $this->messageReceived;
    }

    public function setMessageReceived(bool $messageReceived): self
    {
        $this->messageReceived = $messageReceived;

        return $this;
    }

    public function isPublicationComment(): bool
    {
        return $this->publicationComment;
    }

    public function setPublicationComment(bool $publicationComment): self
    {
        $this->publicationComment = $publicationComment;

        return $this;
    }

    public function isForumReply(): bool
    {
        return $this->forumReply;
    }

    public function setForumReply(bool $forumReply): self
    {
        $this->forumReply = $forumReply;

        return $this;
    }

    public function isMarketing(): bool
    {
        return $this->marketing;
    }

    public function setMarketing(bool $marketing): self
    {
        $this->marketing = $marketing;

        return $this;
    }

    public function isActivityReminder(): bool
    {
        return $this->activityReminder;
    }

    public function setActivityReminder(bool $activityReminder): self
    {
        $this->activityReminder = $activityReminder;

        return $this;
    }

    public function getCreationDatetime(): DateTimeImmutable
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeImmutable $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getUpdateDatetime(): ?DateTimeImmutable
    {
        return $this->updateDatetime;
    }

    public function setUpdateDatetime(?DateTimeImmutable $updateDatetime): self
    {
        $this->updateDatetime = $updateDatetime;

        return $this;
    }
}
