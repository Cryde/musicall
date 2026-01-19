<?php declare(strict_types=1);

namespace App\State\Provider\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Publication\UserPublicationEdit;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\PublicationRepository;
use App\Service\Builder\User\Publication\UserPublicationEditBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<UserPublicationEdit>
 */
class UserPublicationEditProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly PublicationRepository $publicationRepository,
        private readonly UserPublicationEditBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?UserPublicationEdit
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('You must be logged in.');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        if (!$publication = $this->publicationRepository->find($uriVariables['id'])) {
            throw new NotFoundHttpException('Publication not found');
        }

        if ($publication->getAuthor()->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('You are not the owner of this publication');
        }

        if ($publication->getStatus() !== Publication::STATUS_DRAFT) {
            throw new AccessDeniedHttpException('You can only edit draft publications');
        }

        return $this->builder->buildFromEntity($publication);
    }
}
