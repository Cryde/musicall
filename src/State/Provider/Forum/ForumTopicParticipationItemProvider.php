<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Forum\ForumTopicParticipation;
use App\Repository\Forum\ForumTopicParticipationRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ForumTopicParticipation>
 */
readonly class ForumTopicParticipationItemProvider implements ProviderInterface
{
    public function __construct(
        private ForumTopicParticipationRepository $repository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ForumTopicParticipation
    {
        $entity = $this->repository->find($uriVariables['id']);
        if (!$entity instanceof ForumTopicParticipation) {
            throw new NotFoundHttpException('Participation not found');
        }

        return $entity;
    }
}
