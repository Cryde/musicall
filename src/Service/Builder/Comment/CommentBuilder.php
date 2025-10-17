<?php declare(strict_types=1);

namespace App\Service\Builder\Comment;

use App\ApiResource\Comment\CommentCreation;
use App\Entity\Comment\Comment;

class CommentBuilder
{
    public function buildFromModel(CommentCreation $commentCreation): Comment
    {
        return (new Comment())
            ->setContent($commentCreation->content)
            ->setThread($commentCreation->thread);
    }
}
