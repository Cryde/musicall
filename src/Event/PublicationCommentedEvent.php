<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Comment\Comment;
use Symfony\Contracts\EventDispatcher\Event;

class PublicationCommentedEvent extends Event
{
    public function __construct(public readonly Comment $comment)
    {
    }
}
