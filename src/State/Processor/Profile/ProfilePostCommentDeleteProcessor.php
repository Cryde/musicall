<?php

declare(strict_types=1);

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Its author, or the wall's owner, removes a comment; its replies go with it, in the database. Anyone
 * else reads it as a comment that does not exist.
 *
 * @implements ProcessorInterface<mixed, null>
 */
readonly class ProfilePostCommentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProfilePostCommentRepository $commentRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $comment = $this->commentRepository->find((string) $uriVariables['id']);
        if (!$comment instanceof ProfilePostComment
            || ($comment->author->id !== $user->id && $comment->post->author->id !== $user->id)) {
            throw new NotFoundHttpException('profile.wall.comment_not_found');
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        return null;
    }
}
