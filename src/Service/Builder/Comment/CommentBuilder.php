<?php declare(strict_types=1);

namespace App\Service\Builder\Comment;

use App\ApiResource\Comment\CommentCreation;
use App\Entity\Comment\Comment;

class CommentBuilder
{
    public function buildFromModel(CommentCreation $commentCreation): Comment
    {
        $comment = new Comment();
        $comment->content = $commentCreation->content;
        $comment->thread = $commentCreation->thread;

        return $comment;
    }
}
