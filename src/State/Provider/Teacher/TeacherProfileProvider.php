<?php

declare(strict_types=1);

namespace App\State\Provider\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Teacher\Public\TeacherProfile as PublicTeacherProfile;
use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Repository\Service\ServiceRepository;
use App\Repository\UserRepository;
use App\Security\Profile\PublicProfileGuard;
use App\Service\Builder\Teacher\TeacherProfileBuilder;
use App\Service\Profile\ProfileViewTracker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<PublicTeacherProfile>
 */
readonly class TeacherProfileProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private TeacherProfileBuilder $teacherProfileBuilder,
        private ProfileViewTracker $viewTracker,
        private PublicProfileGuard $publicProfileGuard,
        private ServiceRepository $serviceRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicTeacherProfile
    {
        if (!($user = $this->userRepository->findOneBy(['username' => $uriVariables['username'] ?? ''])) instanceof \App\Entity\User) {
            throw new NotFoundHttpException('profile.user_not_found');
        }

        if (!$user->isPubliclyVisible()) {
            throw new NotFoundHttpException('profile.user_not_found');
        }

        $this->publicProfileGuard->assertVisible($user);

        if (!($teacherProfile = $this->serviceRepository->findOneByUserAndKind($user, ServiceKind::Teaching)) instanceof Service) {
            throw new NotFoundHttpException('profile.teacher.not_found');
        }

        $this->viewTracker->track($teacherProfile, $user);

        return $this->teacherProfileBuilder->build($teacherProfile);
    }
}
