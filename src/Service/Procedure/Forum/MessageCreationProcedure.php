<?php

declare(strict_types=1);

namespace App\Service\Procedure\Forum;

use App\ApiResource\Forum\ForumPostResource;
use App\ApiResource\Forum\Topic;
use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use App\Entity\User;
use App\Event\ForumPostCreatedEvent;
use App\Repository\Forum\ForumTopicRepository;
use App\Service\Builder\Forum\ForumPostBuilder;
use App\Service\Forum\ForumTopicParticipationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class MessageCreationProcedure
{
    public function __construct(
        private Security                       $security,
        private ForumPostBuilder               $forumPostBuilder,
        private EntityManagerInterface         $entityManager,
        private ForumTopicRepository           $forumTopicRepository,
        private ForumTopicParticipationService $participationService,
        private EventDispatcherInterface       $eventDispatcher,
    ) {
    }

    public function process(Topic $topicDto, string $message): ForumPostResource
    {
        if (!($topic = $this->forumTopicRepository->find($topicDto->id)) instanceof ForumTopic) {
            throw new NotFoundHttpException('Ce sujet n\'existe pas.');
        }

        if ($topic->isLocked) {
            throw new BadRequestHttpException('Ce sujet est verrouillé. Vous ne pouvez plus y répondre.');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $post = new ForumPost();
        $post->topic = $topic;
        $post->creator = $user;
        $post->content = $message;
        $this->entityManager->persist($post);

        $topic->lastPost = $post;
        $topic->postNumber += 1;
        $topic->forum->postNumber += 1;

        $this->participationService->recordPost($user, $topic);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new ForumPostCreatedEvent($post));

        return $this->forumPostBuilder->buildItem($post);
    }
}
