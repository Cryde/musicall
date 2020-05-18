<?php

namespace App\Service\Builder;

use App\Entity\Comment\CommentThread;

class CommentThreadDirector
{
    public function create(): CommentThread
    {
        return new CommentThread();
    }
}
