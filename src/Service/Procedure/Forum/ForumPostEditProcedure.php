<?php

declare(strict_types=1);

namespace App\Service\Procedure\Forum;

use App\ApiResource\Forum\ForumPostResource;
use App\Entity\Forum\ForumPost;
use App\Entity\User;
use App\Service\Builder\Forum\ForumPostBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class ForumPostEditProcedure
{
    public function __construct(
        private Security               $security,
        private EntityManagerInterface $entityManager,
        private ForumPostBuilder       $forumPostBuilder,
    ) {
    }

    public function process(ForumPost $post, string $content): ForumPostResource
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if (!$this->canEdit($post, $user)) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas modifier ce message.');
        }

        $post->content = $content;
        $post->updateDatetime = new \DateTime();
        $this->entityManager->flush();

        return $this->forumPostBuilder->buildItem($post);
    }

    private function canEdit(ForumPost $post, User $user): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return $post->creator->id === $user->id;
    }
}
