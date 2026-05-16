<?php declare(strict_types=1);

namespace App\Service\Procedure\Forum;

use App\ApiResource\Forum\Forum;
use App\ApiResource\Forum\ForumTopic;
use App\Entity\Forum\ForumPost;
use App\Entity\User;
use App\Repository\Forum\ForumRepository;
use App\Service\Builder\Forum\ForumTopicBuilder;
use App\Service\Builder\Forum\ForumTopicListBuilder;
use App\Service\Forum\ForumTopicParticipationService;
use App\Service\Slugifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TopicCreationProcedure
{
    public function __construct(
        readonly private Security                       $security,
        readonly private ForumTopicBuilder              $forumTopicBuilder,
        readonly private ForumTopicListBuilder          $forumTopicListBuilder,
        readonly private Slugifier                      $slugifier,
        readonly private ForumRepository                $forumRepository,
        readonly private EntityManagerInterface         $entityManager,
        readonly private ForumTopicParticipationService $participationService,
    ) {
    }

    public function process(Forum $forumDto, string $title, string $message): ForumTopic
    {
        if (!($forum = $this->forumRepository->find($forumDto->id)) instanceof \App\Entity\Forum\Forum) {
            throw new NotFoundHttpException('Forum not found.');
        }
        /** @var User $user */
        $user = $this->security->getUser();
        // Create the topic
        $topic = $this->forumTopicBuilder->build($forum, $user, $title);
        $topic->postNumber = 1;
        $topic->slug = $this->slugifier->create($topic, 'title');
        $this->entityManager->persist($topic);
        // create the first post related to the topic
        $post = new ForumPost();
        $post->topic = $topic;
        $post->creator = $user;
        $post->content = $message;
        $this->entityManager->persist($post);
        // set the post as the last post for this topic
        $topic->lastPost = $post;

        // Update forum counters
        $forum->topicNumber += 1;
        $forum->postNumber += 1;

        $this->participationService->recordPost($user, $topic);
        $this->entityManager->flush();

        return $this->forumTopicListBuilder->buildFromEntity($topic);
    }
}
