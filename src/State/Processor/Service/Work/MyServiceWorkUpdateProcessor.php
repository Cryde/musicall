<?php

declare(strict_types=1);

namespace App\State\Processor\Service\Work;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\Work\MyServiceWork;
use App\Entity\User;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MyServiceWorkBuilder;
use App\Service\Procedure\Service\ServiceWorkProcedure;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<MyServiceWork, MyServiceWork>
 */
readonly class MyServiceWorkUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private ServiceWorkProcedure $procedure,
        private MyServiceWorkBuilder $builder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyServiceWork
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $work = $this->ownerChecker->checkOwnerOfWork((string) $uriVariables['serviceId'], (string) $uriVariables['id'], $user);
        $this->procedure->update($work, $data);

        return $this->builder->build($work);
    }
}
