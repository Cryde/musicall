<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\MyService;
use App\Entity\User;
use App\Repository\Service\ServiceRepository;
use App\Service\Builder\Service\MyServiceBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<MyService>
 */
readonly class MyServiceCollectionProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ServiceRepository $serviceRepository,
        private MyServiceBuilder $builder,
    ) {
    }

    /** @return list<MyService> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return array_map($this->builder->build(...), $this->serviceRepository->findByUser($user));
    }
}
