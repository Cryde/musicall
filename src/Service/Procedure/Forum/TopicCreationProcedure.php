<?php

namespace App\Service\Procedure\Forum;

use App\Entity\Forum\Forum;
use App\Entity\Forum\ForumTopic;
use App\Entity\User;
use App\Service\Builder\Forum\ForumPostBuilder;
use App\Service\Builder\Forum\ForumTopicBuilder;
use App\Service\Slugifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class TopicCreationProcedure
{
    public function __construct(
        readonly private Security               $security,
        readonly private ForumTopicBuilder      $forumTopicBuilder,
        readonly private ForumPostBuilder       $forumPostBuilder,
        readonly private Slugifier              $slugifier,
        readonly private EntityManagerInterface $entityManager
    ) {
    }

    public function process(Forum $forum, string $title, string $message): ForumTopic
    {
        /** @var User $user */
        $user = $this->security->getUser();
        // Create the topic
        $topic = $this->forumTopicBuilder->build($forum, $user, $title)->setPostNumber(1);
        $topic->setSlug($this->slugifier->create($topic, 'title'));
        $this->entityManager->persist($topic);
        // create the first post related to the topic
        $post = $this->forumPostBuilder->build($topic, $user, $message);
        $this->entityManager->persist($post);
        // set the post as the last post for this topic
        $topic->setLastPost($post);
        $this->entityManager->flush();

        return $topic;
    }
}