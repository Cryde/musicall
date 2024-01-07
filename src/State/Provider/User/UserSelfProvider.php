<?php

namespace App\State\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserSelfProvider implements ProviderInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous devez être connecté pour accéder à ceci');
        }

        return $this->security->getUser();
    }
}