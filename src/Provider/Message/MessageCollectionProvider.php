<?php

namespace App\Provider\Message;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Access\ThreadAccess;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class MessageCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security                $security,
        private readonly MessageThreadRepository $messageThreadRepository,
        private readonly ThreadAccess            $threadAccess,
        private readonly CollectionProvider      $collectionProvider
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        if ($operation instanceof GetCollection) {
            if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
                throw new AccessDeniedException('Vous n\'êtes pas connecté.');
            }
            /** @var User $user */
            $user = $this->security->getUser();
            $thread = $this->messageThreadRepository->find($uriVariables['threadId']);
            if (!$this->threadAccess->isOneOfParticipant($thread, $user)) {
                throw new AccessDeniedException('Vous n\'êtes pas autorisé à voir ceci.');
            }

            return $this->collectionProvider->provide($operation, $uriVariables, $context);
        }
        throw new \InvalidArgumentException('Operation not supported by the provider');
    }
}