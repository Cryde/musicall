<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\Topic;
use App\Entity\User;
use App\Repository\Forum\ForumTopicRepository;
use App\Service\Builder\Forum\TopicBuilder;
use App\Service\Forum\ForumTopicParticipationService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Topic>
 */
readonly class TopicProvider implements ProviderInterface
{
    public function __construct(
        private ForumTopicRepository           $forumTopicRepository,
        private TopicBuilder                   $topicBuilder,
        private Security                       $security,
        private ForumTopicParticipationService $participationService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Topic
    {
        $slug = $uriVariables['slug'];

        $topic = $this->forumTopicRepository->findOneBy(['slug' => $slug]);

        if (!$topic instanceof \App\Entity\Forum\ForumTopic) {
            throw new NotFoundHttpException('Topic not found');
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $this->participationService->markRead($user, $topic);
        }

        return $this->topicBuilder->buildFromEntity($topic);
    }
}
