<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\Forum;
use App\Repository\Forum\ForumRepository;
use App\Service\Builder\Forum\ForumDetailBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Forum>
 */
readonly class ForumProvider implements ProviderInterface
{
    public function __construct(
        private ForumRepository    $forumRepository,
        private ForumDetailBuilder $forumDetailBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Forum
    {
        $forum = $this->forumRepository->findBySlug($uriVariables['slug']);

        if (!$forum) {
            throw new NotFoundHttpException('Forum not found');
        }

        return $this->forumDetailBuilder->buildFromEntity($forum);
    }
}
