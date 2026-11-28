<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Search\ServiceKindCount;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Repository\Service\ServiceRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<ServiceKindCount>
 */
readonly class ServiceKindCountProvider implements ProviderInterface
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private Security $security,
    ) {
    }

    /** @return list<ServiceKindCount> in the ServiceKind order */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $viewer = $this->security->getUser();
        $counts = $this->serviceRepository->countListedByKind($viewer instanceof User ? $viewer : null);

        return array_map(static function (ServiceKind $kind) use ($counts): ServiceKindCount {
            $kindCount = new ServiceKindCount();
            $kindCount->kind = $kind->value;
            $kindCount->count = $counts[$kind->value] ?? 0;

            return $kindCount;
        }, ServiceKind::cases());
    }
}
