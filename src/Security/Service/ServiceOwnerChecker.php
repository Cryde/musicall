<?php

declare(strict_types=1);

namespace App\Security\Service;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceWork;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServicePart;
use App\Repository\Service\ServiceRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** A member manages their own services only. A 404 for anybody else's, so a service id cannot be probed (#1406). */
readonly class ServiceOwnerChecker
{
    public function __construct(private ServiceRepository $serviceRepository)
    {
    }

    public function checkOwner(string $serviceId, User $user): Service
    {
        $service = $this->serviceRepository->find($serviceId);
        if (!$service instanceof Service || $service->user->id !== $user->id) {
            throw new NotFoundHttpException('service.not_found');
        }

        return $service;
    }

    /** Their own service of that kind: a kind's form never edits another kind's service (#1410). */
    public function checkOwnerOfKind(string $serviceId, User $user, ServiceKind $kind): Service
    {
        $service = $this->checkOwner($serviceId, $user);
        if ($service->kind !== $kind) {
            throw new NotFoundHttpException('service.not_found');
        }

        return $service;
    }

    /** Their own service of a kind that has the part, such as « Avant, après » (#1410). */
    public function checkOwnerWithPart(string $serviceId, User $user, ServicePart $part): Service
    {
        $service = $this->checkOwner($serviceId, $user);
        if (!in_array($part, $service->kind->parts(), true)) {
            throw new NotFoundHttpException('service.not_found');
        }

        return $service;
    }

    /** A work of their own service (#1407): a work of another service is not found, even the same member's. */
    public function checkOwnerOfWork(string $serviceId, string $workId, User $user): ServiceWork
    {
        $service = $this->checkOwnerWithPart($serviceId, $user, ServicePart::Works);
        $work = $service->work($workId);
        if (!$work instanceof ServiceWork) {
            throw new NotFoundHttpException('service.work.not_found');
        }

        return $work;
    }
}
