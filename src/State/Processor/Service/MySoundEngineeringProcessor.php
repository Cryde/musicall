<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\SoundEngineering\MySoundEngineering;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MySoundEngineeringBuilder;
use App\Service\Service\SoundEngineeringWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<MySoundEngineering, MySoundEngineering>
 */
readonly class MySoundEngineeringProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private SoundEngineeringWriter $writer,
        private EntityManagerInterface $entityManager,
        private MySoundEngineeringBuilder $builder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MySoundEngineering
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $service = $this->ownerChecker->checkOwnerOfKind((string) $uriVariables['id'], $user, ServiceKind::SoundEngineering);

        $this->writer->write($service, $data);
        $this->entityManager->flush();

        return $this->builder->build($service);
    }
}
