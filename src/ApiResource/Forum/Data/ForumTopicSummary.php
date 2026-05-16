<?php

declare(strict_types=1);

namespace App\ApiResource\Forum\Data;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeInterface;

class ForumTopicSummary
{
    public string $id;
    public string $title;
    public string $slug;

    public bool $isLocked;
    public bool $isResolved;
    public bool $isPinned;

    public int $postNumber;
    public DateTimeInterface $creationDatetime;

    #[ApiProperty(genId: false)]
    public ?ForumPost $lastPost = null;

    #[ApiProperty(genId: false)]
    public User $author;

    #[ApiProperty(genId: false)]
    public Forum $forum;
}
