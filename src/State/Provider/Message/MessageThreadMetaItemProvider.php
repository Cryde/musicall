<?php

declare(strict_types=1);

namespace App\State\Provider\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Message\MessageThreadMetaResource;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\Builder\Message\MessageThreadMetaBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<MessageThreadMetaResource>
 */
readonly class MessageThreadMetaItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private MessageThreadMetaBuilder    $messageThreadMetaBuilder,
        private Security                    $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MessageThreadMetaResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }

        // The id is a uuid by route requirement, so nothing unconvertible reaches Doctrine here.
        $entity = $this->messageThreadMetaRepository->findOneByIdAndUser((string) $uriVariables['id'], $user);
        if (!$entity instanceof MessageThreadMeta) {
            throw new NotFoundHttpException('Message thread meta introuvable');
        }

        return $this->messageThreadMetaBuilder->buildItem($entity);
    }
}
