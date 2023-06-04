<?php

namespace App\Provider\Message;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\Message\MessageThreadMetaRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class MessageThreadMetaCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly MessageThreadMetaRepository $messageThreadMetaRepository,
        private readonly Security                    $security
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

            return $this->messageThreadMetaRepository->findByUserAndNotDeleted($user);
        }
        throw new \InvalidArgumentException('Operation not supported by the provider');
    }
}