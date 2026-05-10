<?php

declare(strict_types=1);

namespace App\State\Provider\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Message\MessageThreadResource;
use App\Entity\Message\MessageThread;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Builder\Message\MessageThreadBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MessageThreadResource>
 */
readonly class MessageThreadItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageThreadRepository $messageThreadRepository,
        private MessageThreadBuilder    $messageThreadBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MessageThreadResource
    {
        $entity = $this->messageThreadRepository->find($uriVariables['id']);
        if (!$entity instanceof MessageThread) {
            throw new NotFoundHttpException('Thread not found.');
        }

        return $this->messageThreadBuilder->buildItem($entity);
    }
}
