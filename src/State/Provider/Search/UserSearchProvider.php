<?php

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\UserRepository;
use App\Service\Builder\Search\UserSearchBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class UserSearchProvider implements ProviderInterface
{
    public function __construct(
        private Security          $security,
        private UserRepository    $userRepository,
        private UserSearchBuilder $userSearchBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous devez être connecté pour accéder à ceci');
        }
        $users = $this->userRepository->searchByUserName($context['filters']['search']);

        return $this->userSearchBuilder->buildList($users);
    }
}