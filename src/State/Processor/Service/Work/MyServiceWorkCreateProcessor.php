<?php

declare(strict_types=1);

namespace App\State\Processor\Service\Work;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\Work\MyServiceWork;
use App\Entity\Service\ServiceWork;
use App\Entity\User;
use App\Enum\Service\ServicePart;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MyServiceWorkBuilder;
use App\Service\Procedure\Service\ServiceWorkProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<MyServiceWork, MyServiceWork>
 */
readonly class MyServiceWorkCreateProcessor implements ProcessorInterface
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
        $service = $this->ownerChecker->checkOwnerWithPart((string) $uriVariables['serviceId'], $user, ServicePart::Works);
        if ($service->works->count() >= ServiceWork::MAX_PER_SERVICE) {
            throw new UnprocessableEntityHttpException('service.work.limit_reached');
        }

        return $this->builder->build($this->procedure->create($service, $data));
    }
}
