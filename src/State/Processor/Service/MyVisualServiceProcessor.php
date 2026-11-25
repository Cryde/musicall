<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\Visual\MyVisualService;
use App\Entity\User;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MyVisualServiceBuilder;
use App\Service\Service\VisualServiceWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<MyVisualService, MyVisualService>
 */
readonly class MyVisualServiceProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private VisualServiceWriter $writer,
        private EntityManagerInterface $entityManager,
        private MyVisualServiceBuilder $builder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyVisualService
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $service = $this->ownerChecker->checkOwnerOfKind((string) $uriVariables['id'], $user, $data::kind());

        $this->writer->write($service, $data);
        $this->entityManager->flush();

        return $this->builder->build($service, $data);
    }
}
