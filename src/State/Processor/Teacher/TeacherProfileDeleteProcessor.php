<?php

declare(strict_types=1);

namespace App\State\Processor\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Teacher\Private\TeacherProfileOutput;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Repository\Service\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TeacherProfileOutput, null>
 */
readonly class TeacherProfileDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private ServiceRepository $serviceRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $this->serviceRepository->findOneByUserAndKind($user, ServiceKind::Teaching);

        if (!$profile) {
            throw new NotFoundHttpException('profile.teacher.not_found');
        }

        $this->entityManager->remove($profile);
        $this->entityManager->flush();

        return null;
    }
}
