<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Security\Service\ServiceOwnerChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class MyServiceDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $service = $this->ownerChecker->checkOwner((string) $uriVariables['id'], $user);

        $this->entityManager->remove($service);
        $this->entityManager->flush();

        return null;
    }
}
