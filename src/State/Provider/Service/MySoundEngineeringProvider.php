<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\SoundEngineering\MySoundEngineering;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MySoundEngineeringBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<MySoundEngineering>
 */
readonly class MySoundEngineeringProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private MySoundEngineeringBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MySoundEngineering
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->builder->build($this->ownerChecker->checkOwnerOfKind((string) $uriVariables['id'], $user, ServiceKind::SoundEngineering));
    }
}
