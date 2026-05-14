<?php

declare(strict_types=1);

namespace App\State\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Forum\ForumTopic;
use App\Repository\Forum\ForumTopicRepository;
use App\Service\Procedure\Forum\ForumTopicLockProcedure;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class ForumTopicLockProcessor implements ProcessorInterface
{
    public function __construct(
        private ForumTopicRepository    $forumTopicRepository,
        private ForumTopicLockProcedure $forumTopicLockProcedure,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $topic = $this->forumTopicRepository->findOneBy(['slug' => $uriVariables['slug']]);
        if (!$topic instanceof ForumTopic) {
            throw new NotFoundHttpException('Topic not found');
        }

        $this->forumTopicLockProcedure->process($topic, true);

        return null;
    }
}
