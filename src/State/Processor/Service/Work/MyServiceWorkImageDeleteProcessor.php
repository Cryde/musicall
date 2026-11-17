<?php

declare(strict_types=1);

namespace App\State\Processor\Service\Work;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Service\ServiceWorkImage;
use App\Entity\User;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Procedure\Service\ServiceWorkProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class MyServiceWorkImageDeleteProcessor implements ProcessorInterface
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
        $image = $work->image((string) $uriVariables['id']);
        if (!$image instanceof ServiceWorkImage) {
            throw new NotFoundHttpException('service.work.image_not_found');
        }
        $this->procedure->removeImage($image);

        return null;
    }
}
