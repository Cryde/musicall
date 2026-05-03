<?php declare(strict_types=1);

namespace App\Service\Procedure\Comment;

use App\ApiResource\Comment\CommentCreation;
use App\Entity\Comment\Comment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

readonly class CreateCommentProcedure
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function process(User $user, CommentCreation $commentCreation): Comment
    {
        $comment = new Comment();
        $comment->content = $commentCreation->content;
        $comment->thread = $commentCreation->thread;
        $comment->author = $user;
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }
}
