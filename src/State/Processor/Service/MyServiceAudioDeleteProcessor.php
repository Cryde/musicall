<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\Service\BeforeAfterSide;
use App\Enum\Service\ServicePart;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Procedure\Service\ServiceAudioProcedure;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Deleting a side that holds nothing is a 204 too: what the owner wanted is already true.
 *
 * @implements ProcessorInterface<mixed, null>
 */
readonly class MyServiceAudioDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private ServiceAudioProcedure $procedure,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $service = $this->ownerChecker->checkOwnerWithPart((string) $uriVariables['id'], $user, ServicePart::BeforeAfter);
        $this->procedure->remove($service, BeforeAfterSide::from((string) $uriVariables['side']));

        return null;
    }
}
