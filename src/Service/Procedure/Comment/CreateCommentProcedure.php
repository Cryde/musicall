<?php declare(strict_types=1);

namespace App\Service\Procedure\Comment;

use App\ApiResource\Comment\CommentCreation;
use App\Entity\Comment\Comment;
use App\Entity\User;
use App\Service\Builder\Comment\CommentBuilder;
use Doctrine\ORM\EntityManagerInterface;

readonly class CreateCommentProcedure
{
    public function __construct(private CommentBuilder $commentBuilder, private EntityManagerInterface $entityManager)
    {
    }

    public function process(User $user, CommentCreation $commentCreation): Comment
    {
        $comment = $this->commentBuilder->buildFromModel($commentCreation);
        $comment->setAuthor($user);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }
}
