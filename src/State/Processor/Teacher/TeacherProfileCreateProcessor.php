<?php

declare(strict_types=1);

namespace App\State\Processor\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Teacher\Private\TeacherProfileInput;
use App\ApiResource\Teacher\Private\TeacherProfileOutput;
use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Repository\Service\ServiceRepository;
use App\Service\Teacher\TeacherProfileWriter;
use App\State\Provider\Teacher\TeacherProfileEditProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<TeacherProfileInput, TeacherProfileOutput>
 */
readonly class TeacherProfileCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private TeacherProfileEditProvider $outputProvider,
        private TeacherProfileWriter $profileWriter,
        private ServiceRepository $serviceRepository,
        private ClockInterface $clock,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TeacherProfileOutput
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException();
        }

        if ($this->serviceRepository->findOneByUserAndKind($user, ServiceKind::Teaching) instanceof Service) {
            throw new BadRequestHttpException('profile.teacher.already_exists');
        }

        $profile = new Service();
        $profile->user = $user;
        $profile->kind = ServiceKind::Teaching;
        $profile->position = $this->serviceRepository->nextPositionFor($user);
        // The app knows nothing of drafts: a teacher profile it creates goes online, as it always did (#1406).
        $profile->publish($this->clock->now());

        $this->profileWriter->write($profile, $data);

        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $this->outputProvider->buildFromEntity($profile);
    }
}
