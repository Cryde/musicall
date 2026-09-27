<?php

declare(strict_types=1);

namespace App\ApiResource\Forum\Recent;

/** A topic as the logged in home lists it (#1078): where it is, and how much it moved. */
class RecentForumTopic
{
    public string $title;
    public string $slug;
    public string $forumTitle;
    public string $forumSlug;
    /** The posts after the opening one. */
    public int $replies = 0;
    public \DateTimeInterface $lastActivityDatetime;
}
