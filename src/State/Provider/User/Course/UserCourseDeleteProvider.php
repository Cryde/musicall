<?php declare(strict_types=1);

namespace App\State\Provider\User\Course;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\PublicationRepository;
use App\Service\Builder\User\Course\UserCourseBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<\App\ApiResource\User\Course\UserCourse>
 */
class UserCourseDeleteProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly PublicationRepository $publicationRepository,
        private readonly UserCourseBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('You must be logged in.');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        if (!$publication = $this->publicationRepository->find($uriVariables['id'])) {
            throw new NotFoundHttpException('Course not found');
        }

        if ($publication->getAuthor()->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('You are not the owner of this course');
        }

        if ($publication->getStatus() !== Publication::STATUS_DRAFT) {
            throw new AccessDeniedHttpException('You can only delete draft courses');
        }

        return $this->builder->buildFromEntity($publication);
    }
}
