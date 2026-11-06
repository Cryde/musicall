<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Profile\ProfilePostComment;

/** Dispatched once a comment on a wall post, or a reply, is stored (#1316). */
final readonly class ProfilePostCommentedEvent
{
    public function __construct(public ProfilePostComment $comment)
    {
    }
}
