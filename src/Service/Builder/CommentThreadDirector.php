<?php declare(strict_types=1);

namespace App\Service\Builder;

use App\Entity\Comment\CommentThread;

class CommentThreadDirector
{
    public function create(): CommentThread
    {
        return new CommentThread();
    }
}
