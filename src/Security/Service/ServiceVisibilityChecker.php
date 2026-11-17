<?php

declare(strict_types=1);

namespace App\Security\Service;

use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Repository\Service\ServiceRepository;
use App\Repository\UserRepository;
use App\Security\Profile\PublicProfileGuard;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * What a visitor may read of a service page: a published service of a member anyone may see, unless the profile is
 * private (#1317). Its owner also reads their draft (#1406).
 */
readonly class ServiceVisibilityChecker
{
    public function __construct(
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private PublicProfileGuard $publicProfileGuard,
    ) {
    }

    public function checkVisibleByUsername(string $username, ServiceKind $kind, string $notFoundKey = 'service.not_found'): Service
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user instanceof User || !$user->isPubliclyVisible()) {
            throw new NotFoundHttpException('profile.user_not_found');
        }
        $this->publicProfileGuard->assertVisible($user);

        $service = $this->serviceRepository->findOneByUserAndKind($user, $kind);
        if (!$service instanceof Service || !$this->isReadable($service)) {
            throw new NotFoundHttpException($notFoundKey);
        }

        return $service;
    }

    public function checkVisible(string $serviceId): Service
    {
        $service = $this->serviceRepository->find($serviceId);
        if (!$service instanceof Service || !$service->user->isPubliclyVisible() || !$this->isReadable($service)) {
            throw new NotFoundHttpException('service.not_found');
        }
        $this->publicProfileGuard->assertVisible($service->user);

        return $service;
    }

    private function isReadable(Service $service): bool
    {
        return $service->isPublished() || $this->publicProfileGuard->isViewedByOwner($service->user);
    }
}
