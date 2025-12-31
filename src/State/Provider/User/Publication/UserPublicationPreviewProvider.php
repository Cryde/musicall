<?php declare(strict_types=1);

namespace App\State\Provider\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Publication\UserPublicationPreview;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\PublicationRepository;
use App\Service\Builder\User\Publication\UserPublicationPreviewBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<UserPublicationPreview>
 */
class UserPublicationPreviewProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly PublicationRepository $publicationRepository,
        private readonly UserPublicationPreviewBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?UserPublicationPreview
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('You must be logged in.');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $publication = $this->publicationRepository->find($uriVariables['id']);
        if (!$publication) {
            throw new NotFoundHttpException('Publication not found');
        }

        if ($publication->getAuthor()->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('You are not the owner of this publication');
        }

        // Preview is only available for draft or pending publications
        if ($publication->getStatus() === Publication::STATUS_ONLINE) {
            throw new AccessDeniedHttpException('This publication is already online. View it directly.');
        }

        return $this->builder->buildFromEntity($publication);
    }
}
