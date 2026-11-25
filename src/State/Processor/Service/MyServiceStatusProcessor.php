<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\MyService;
use App\Entity\User;
use App\Enum\Service\ServiceStatus;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MyServiceBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Publishes or unpublishes, to the status the operation names. A draft goes online once its kind has what it needs;
 * one already online stays so: a teacher profile online before drafts existed may miss a part, and is never taken
 * down for it (#1406).
 *
 * @implements ProcessorInterface<mixed, MyService>
 */
readonly class MyServiceStatusProcessor implements ProcessorInterface
{
    public const string TARGET_STATUS = 'target_status';

    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private EntityManagerInterface $entityManager,
        private MyServiceBuilder $builder,
        private ClockInterface $clock,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyService
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $service = $this->ownerChecker->checkOwner((string) $uriVariables['id'], $user);
        $target = $operation->getExtraProperties()[self::TARGET_STATUS] ?? null;
        if (!$target instanceof ServiceStatus) {
            throw new \LogicException(sprintf('The operation %s names no %s.', (string) $operation->getName(), self::TARGET_STATUS));
        }

        if ($target === ServiceStatus::Published && !$service->isPublished() && $service->missingToPublish() !== []) {
            throw new UnprocessableEntityHttpException('service.publish.incomplete');
        }
        if ($target === ServiceStatus::Published) {
            $service->publish($this->clock->now());
        } else {
            $service->status = $target;
        }
        $this->entityManager->flush();

        return $this->builder->build($service);
    }
}
