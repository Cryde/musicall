<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

/** How a post's top-level comments are paged (#1331). Replies stay in date order under their comment. */
enum CommentOrder: string
{
    case Recent = 'recent';
}
