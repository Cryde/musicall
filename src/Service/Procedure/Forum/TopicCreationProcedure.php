<?php declare(strict_types=1);

namespace App\Service\Procedure\Forum;

use App\ApiResource\Forum\Forum;
use App\ApiResource\Forum\ForumTopic;
use App\Entity\User;
use App\Repository\Forum\ForumRepository;
use App\Service\Builder\Forum\ForumPostBuilder;
use App\Service\Builder\Forum\ForumTopicBuilder;
use App\Service\Builder\Forum\ForumTopicListBuilder;
use App\Service\Slugifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class TopicCreationProcedure
{
    public function __construct(
        readonly private Security               $security,
        readonly private ForumTopicBuilder      $forumTopicBuilder,
        readonly private ForumPostBuilder       $forumPostBuilder,
        readonly private ForumTopicListBuilder  $forumTopicListBuilder,
        readonly private Slugifier              $slugifier,
        readonly private ForumRepository        $forumRepository,
        readonly private EntityManagerInterface $entityManager
    ) {
    }

    public function process(Forum $forumDto, string $title, string $message): ForumTopic
    {
        $forum = $this->forumRepository->find($forumDto->id);
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

        // Update forum counters
        $forum->setTopicNumber($forum->getTopicNumber() + 1);
        $forum->setPostNumber($forum->getPostNumber() + 1);

        $this->entityManager->flush();

        return $this->forumTopicListBuilder->buildFromEntity($topic);
    }
}
