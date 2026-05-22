<?php declare(strict_types=1);

namespace App\Service\Procedure\Comment;

use App\ApiResource\Comment\CommentCreation;
use App\Entity\Comment\Comment;
use App\Entity\User;
use App\Repository\Comment\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class CreateCommentProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommentRepository      $commentRepository,
    ) {
    }

    public function process(User $user, CommentCreation $commentCreation): Comment
    {
        // ValidReplyParent has already enforced existence + same thread + no nesting on
        // $commentCreation->parentId by the time we get here. The find() call below hits
        // the Doctrine identity map (already loaded by the validator), so no extra query.
        $parent = $commentCreation->parentId !== null
            ? $this->commentRepository->find($commentCreation->parentId)
            : null;

        $comment = new Comment();
        $comment->content = $commentCreation->content;
        $comment->thread = $commentCreation->thread;
        $comment->author = $user;
        $comment->parent = $parent;
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }
}
