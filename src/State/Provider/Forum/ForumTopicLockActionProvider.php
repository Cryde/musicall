<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Forum\ForumTopic;
use App\Repository\Forum\ForumTopicRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ForumTopic>
 */
readonly class ForumTopicLockActionProvider implements ProviderInterface
{
    public function __construct(
        private ForumTopicRepository $forumTopicRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ForumTopic
    {
        $topic = $this->forumTopicRepository->findOneBy(['slug' => $uriVariables['slug']]);

        if (!$topic instanceof ForumTopic) {
            throw new NotFoundHttpException('Topic not found');
        }

        return $topic;
    }
}
