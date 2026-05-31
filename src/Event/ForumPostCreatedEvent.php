<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Forum\ForumPost;
use Symfony\Contracts\EventDispatcher\Event;

class ForumPostCreatedEvent extends Event
{
    public function __construct(public readonly ForumPost $forumPost)
    {
    }
}
