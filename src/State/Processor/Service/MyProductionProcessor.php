<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\Production\MyProduction;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MyServiceFormBuilder;
use App\Service\Service\ServiceWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Writes a production service from its form (#1413): what every kind's form writes, and nothing else.
 *
 * @implements ProcessorInterface<MyProduction, MyProduction>
 */
readonly class MyProductionProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private ServiceWriter $writer,
        private EntityManagerInterface $entityManager,
        private MyServiceFormBuilder $formBuilder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyProduction
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $service = $this->ownerChecker->checkOwnerOfKind((string) $uriVariables['id'], $user, ServiceKind::Production);

        $this->writer->writeForm($service, $data);
        $this->entityManager->flush();
        // Read back as stored: a blank headline comes back null, the offers by price.
        $this->formBuilder->fill($data, $service);

        return $data;
    }
}
