<?php

declare(strict_types=1);

namespace App\State\Processor\Service\Work;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\Work\MyServiceWorkOrder;
use App\Entity\User;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Procedure\Service\ServiceWorkProcedure;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<MyServiceWorkOrder, null>
 */
readonly class MyServiceWorkImageReorderProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private ServiceWorkProcedure $procedure,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $work = $this->ownerChecker->checkOwnerOfWork((string) $uriVariables['serviceId'], (string) $uriVariables['workId'], $user);
        $this->procedure->reorderImages($work, array_values($data->ids));

        return null;
    }
}
