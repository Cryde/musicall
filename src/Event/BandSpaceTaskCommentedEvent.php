<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\TaskComment;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class BandSpaceTaskCommentedEvent extends Event
{
    /**
     * @param User[] $mentionedUsers users already @-mentioned in this comment; excluded from the
     *                               participant fan-out so they receive only the richer task-mention
     *                               notification (#717), never a duplicate
     */
    public function __construct(
        public readonly TaskComment $comment,
        public readonly array $mentionedUsers,
    ) {
    }
}
