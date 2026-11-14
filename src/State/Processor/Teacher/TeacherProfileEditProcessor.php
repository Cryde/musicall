<?php

declare(strict_types=1);

namespace App\State\Processor\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Teacher\Private\TeacherProfileInput;
use App\ApiResource\Teacher\Private\TeacherProfileOutput;
use App\Entity\User;
use App\Service\Teacher\TeacherProfileWriter;
use App\State\Provider\Teacher\TeacherProfileEditProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TeacherProfileInput, TeacherProfileOutput>
 */
readonly class TeacherProfileEditProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private TeacherProfileEditProvider $outputProvider,
        private TeacherProfileWriter $profileWriter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TeacherProfileOutput
    {
        /** @var TeacherProfileInput $data */
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->teacherProfile;

        if (!$profile) {
            throw new NotFoundHttpException('profile.teacher.not_found');
        }

        $this->profileWriter->write($profile, $data);
        $this->entityManager->flush();

        return $this->outputProvider->buildFromEntity($profile);
    }
}
