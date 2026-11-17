<?php

declare(strict_types=1);

namespace App\State\Provider\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Teacher\Public\TeacherProfile as PublicTeacherProfile;
use App\Enum\Service\ServiceKind;
use App\Security\Service\ServiceVisibilityChecker;
use App\Service\Builder\Teacher\TeacherProfileBuilder;
use App\Service\Profile\ProfileViewTracker;

/**
 * @implements ProviderInterface<PublicTeacherProfile>
 */
readonly class TeacherProfileProvider implements ProviderInterface
{
    public function __construct(
        private TeacherProfileBuilder $teacherProfileBuilder,
        private ProfileViewTracker $viewTracker,
        private ServiceVisibilityChecker $visibilityChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicTeacherProfile
    {
        $teacherProfile = $this->visibilityChecker->checkVisibleByUsername(
            (string) ($uriVariables['username'] ?? ''),
            ServiceKind::Teaching,
            'profile.teacher.not_found',
        );

        $this->viewTracker->track($teacherProfile, $teacherProfile->user);

        return $this->teacherProfileBuilder->build($teacherProfile);
    }
}
