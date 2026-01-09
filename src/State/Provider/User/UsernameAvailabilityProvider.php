<?php

declare(strict_types=1);

namespace App\State\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\UsernameAvailability;
use App\Repository\UserRepository;

/**
 * @implements ProviderInterface<UsernameAvailability>
 */
readonly class UsernameAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UsernameAvailability
    {
        $username = $uriVariables['username'];

        $dto = new UsernameAvailability();
        $dto->username = $username;
        $dto->available = $this->userRepository->findOneBy(['username' => $username]) === null;

        return $dto;
    }
}
