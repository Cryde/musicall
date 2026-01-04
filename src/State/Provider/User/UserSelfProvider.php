<?php

declare(strict_types=1);

namespace App\State\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\UserSelf;
use App\Entity\User;
use App\Service\Builder\User\UserSelfBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class UserSelfProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private UserSelfBuilder $userSelfBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserSelf
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        return $this->userSelfBuilder->buildFromEntity($user);
    }
}
