<?php

declare(strict_types=1);

namespace App\State\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Forum\ForumTopicParticipation;
use App\Entity\User;
use App\Repository\Forum\ForumTopicParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class ForumTopicParticipationRestoreProcessor implements ProcessorInterface
{
    public function __construct(
        private ForumTopicParticipationRepository $repository,
        private EntityManagerInterface            $entityManager,
        private Security                          $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $participation = $this->repository->find($uriVariables['id']);
        if (!$participation instanceof ForumTopicParticipation) {
            throw new NotFoundHttpException('Participation not found');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        if ($participation->user->id !== $user->id) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas modifier cette participation.');
        }

        if ($participation->removedDatetime !== null) {
            $participation->removedDatetime = null;
            $this->entityManager->flush();
        }

        return null;
    }
}
