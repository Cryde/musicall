<?php

declare(strict_types=1);

namespace App\State\Provider\Service\Work;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Work\MyServiceWork;
use App\Entity\User;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MyServiceWorkBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<MyServiceWork>
 */
readonly class MyServiceWorkProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private MyServiceWorkBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MyServiceWork
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->builder->build($this->ownerChecker->checkOwnerOfWork((string) $uriVariables['serviceId'], (string) $uriVariables['id'], $user));
    }
}
