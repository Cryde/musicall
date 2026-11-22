<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\MyServiceOrder;
use App\Entity\User;
use App\Repository\Service\ServiceRepository;
use App\Service\Service\PositionOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Only the member's own services can be ordered: an id of anyone else's is unknown, so the order is refused whole.
 *
 * @implements ProcessorInterface<MyServiceOrder, null>
 */
readonly class MyServiceReorderProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceRepository $serviceRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        PositionOrder::apply($this->serviceRepository->findByUser($user), array_values($data->ids));
        $this->entityManager->flush();

        return null;
    }
}
