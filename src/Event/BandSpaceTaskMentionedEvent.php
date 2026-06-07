<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\TaskComment;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class BandSpaceTaskMentionedEvent extends Event
{
    /**
     * @param User[] $mentionedUsers active band-space members mentioned in the comment (may include the author)
     */
    public function __construct(
        public readonly TaskComment $comment,
        public readonly array $mentionedUsers,
    ) {
    }
}
