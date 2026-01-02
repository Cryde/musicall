<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\Topic;
use App\Repository\Forum\ForumTopicRepository;
use App\Service\Builder\Forum\TopicBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Topic>
 */
readonly class TopicProvider implements ProviderInterface
{
    public function __construct(
        private ForumTopicRepository $forumTopicRepository,
        private TopicBuilder         $topicBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Topic
    {
        $slug = $uriVariables['slug'];

        $topic = $this->forumTopicRepository->findOneBy(['slug' => $slug]);

        if (!$topic) {
            throw new NotFoundHttpException('Topic not found');
        }

        return $this->topicBuilder->buildFromEntity($topic);
    }
}
