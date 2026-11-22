<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\MyService;
use App\ApiResource\Service\MyServiceInput;
use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Repository\Service\ServiceRepository;
use App\Service\Builder\Service\MyServiceBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * A new service starts as a draft, empty, after the member's others: its kind's form fills it in.
 *
 * @implements ProcessorInterface<MyServiceInput, MyService>
 */
readonly class MyServiceCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private MyServiceBuilder $builder,
        private ServiceRepository $serviceRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyService
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $service = new Service();
        $service->user = $user;
        $service->kind = ServiceKind::from((string) $data->kind);
        $service->position = $this->serviceRepository->nextPositionFor($user);

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        return $this->builder->build($service);
    }
}
