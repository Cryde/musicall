<?php

declare(strict_types=1);

namespace App\Service\Procedure\Forum;

use App\Entity\Forum\ForumTopic;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class ForumTopicLockProcedure
{
    public function __construct(
        private Security               $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(ForumTopic $topic, bool $locked): void
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if (!$this->canManage($topic, $user)) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas modifier l\'état de ce sujet.');
        }

        if ($topic->isLocked === $locked) {
            return;
        }

        $topic->isLocked = $locked;
        $this->entityManager->flush();
    }

    private function canManage(ForumTopic $topic, User $user): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return $topic->author->id === $user->id;
    }
}
