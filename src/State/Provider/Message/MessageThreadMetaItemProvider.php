<?php

declare(strict_types=1);

namespace App\State\Provider\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Message\MessageThreadMetaResource;
use App\Entity\Message\MessageThreadMeta;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\Builder\Message\MessageThreadMetaBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MessageThreadMetaResource>
 */
readonly class MessageThreadMetaItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private MessageThreadMetaBuilder    $messageThreadMetaBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MessageThreadMetaResource
    {
        $entity = $this->messageThreadMetaRepository->find($uriVariables['id']);
        if (!$entity instanceof MessageThreadMeta) {
            throw new NotFoundHttpException('Message thread meta introuvable');
        }

        return $this->messageThreadMetaBuilder->buildItem($entity);
    }
}
