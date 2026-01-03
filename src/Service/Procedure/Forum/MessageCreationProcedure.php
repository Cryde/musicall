<?php

declare(strict_types=1);

namespace App\Service\Procedure\Forum;

use App\ApiResource\Forum\Topic;
use App\ApiResource\Forum\TopicPost;
use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use App\Entity\User;
use App\Repository\Forum\ForumTopicRepository;
use App\Service\Builder\Forum\ForumPostBuilder;
use App\Service\Builder\Forum\TopicPostListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class MessageCreationProcedure
{
    public function __construct(
        private Security               $security,
        private ForumPostBuilder       $forumPostBuilder,
        private TopicPostListBuilder $topicPostListBuilder,
        private EntityManagerInterface $entityManager,
        private ForumTopicRepository $forumTopicRepository,
    ) {
    }

    public function process(Topic $topicDto, string $message): TopicPost
    {
        $topic = $this->forumTopicRepository->find($topicDto->id);

        if ($topic->getIsLocked()) {
            throw new BadRequestHttpException('Ce sujet est verrouillé. Vous ne pouvez plus y répondre.');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $post = $this->forumPostBuilder->build($topic, $user, $message);
        $this->entityManager->persist($post);

        // Update topic counters
        $topic->setLastPost($post);
        $topic->setPostNumber($topic->getPostNumber() + 1);

        // Update forum counters
        $forum = $topic->getForum();
        $forum->setPostNumber($forum->getPostNumber() + 1);

        $this->entityManager->flush();

        return $this->topicPostListBuilder->buildFromEntity($post);
    }
}
