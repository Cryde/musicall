<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Visual\MyVisualService;
use App\Entity\User;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MyVisualServiceBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * A photography or video service's form (#1411), the kind named by the operation's resource.
 *
 * @implements ProviderInterface<MyVisualService>
 */
readonly class MyVisualServiceProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private MyVisualServiceBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MyVisualService
    {
        /** @var User $user */
        $user = $this->security->getUser();
        /** @var class-string<MyVisualService> $resourceClass */
        $resourceClass = $operation->getClass();

        return $this->builder->build(
            $this->ownerChecker->checkOwnerOfKind((string) $uriVariables['id'], $user, $resourceClass::kind()),
            new $resourceClass(),
        );
    }
}
