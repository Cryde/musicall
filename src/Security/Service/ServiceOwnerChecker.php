<?php

declare(strict_types=1);

namespace App\Security\Service;

use App\Entity\Service\Service;
use App\Entity\User;
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
}
