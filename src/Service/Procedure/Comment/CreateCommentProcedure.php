<?php declare(strict_types=1);

namespace App\Service\Procedure\Comment;

use App\ApiResource\Comment\CommentCreation;
use App\Entity\Comment\Comment;
use App\Entity\User;
use App\Event\PublicationCommentedEvent;
use App\Repository\Comment\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class CreateCommentProcedure
{
    public function __construct(
        private EntityManagerInterface   $entityManager,
        private CommentRepository        $commentRepository,
        private EventDispatcherInterface $eventDispatcher,
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

        // Dispatched after the commit so the notification side-effect can never roll back the
        // comment (epic #689 contract). Recipients + publication are resolved in the listener.
        $this->eventDispatcher->dispatch(new PublicationCommentedEvent($comment));

        return $comment;
    }
}
