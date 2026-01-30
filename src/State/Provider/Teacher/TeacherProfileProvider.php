<?php

declare(strict_types=1);

namespace App\State\Provider\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Teacher\Public\TeacherProfile as PublicTeacherProfile;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Builder\Teacher\TeacherProfileBuilder;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<PublicTeacherProfile>
 */
readonly class TeacherProfileProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private TeacherProfileBuilder $teacherProfileBuilder,
        private ViewProcedure $viewProcedure,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicTeacherProfile
    {
        if (!$user = $this->userRepository->findOneBy(['username' => $uriVariables['username'] ?? ''])) {
            throw new NotFoundHttpException('Utilisateur non trouvé');
        }

        if (!$teacherProfile = $user->getTeacherProfile()) {
            throw new NotFoundHttpException('Profil professeur non trouvé');
        }

        $this->trackView($teacherProfile, $user);

        return $this->teacherProfileBuilder->build($teacherProfile);
    }

    private function trackView(TeacherProfile $profile, User $profileOwner): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();

        // Don't count own views
        if ($currentUser && $currentUser->getId() === $profileOwner->getId()) {
            return;
        }

        $this->viewProcedure->process($profile, $request, $currentUser);
    }
}
