<?php

declare(strict_types=1);

namespace App\Service\Procedure\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Event\ProfilePostCommentedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class CreateProfilePostCommentProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(ProfilePost $post, User $author, string $content, ?ProfilePostComment $parent): ProfilePostComment
    {
        $comment = new ProfilePostComment();
        $comment->post = $post;
        $comment->author = $author;
        $comment->parent = $parent;
        $comment->content = trim($content);

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new ProfilePostCommentedEvent($comment));

        return $comment;
    }
}
