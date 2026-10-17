<?php declare(strict_types=1);

namespace App\State\Provider\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\PublicationRepository;
use App\Service\Builder\User\Publication\UserPublicationBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<\App\ApiResource\User\Publication\UserPublication>
 */
class UserPublicationDeleteProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly PublicationRepository $publicationRepository,
        private readonly UserPublicationBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('publication.not_authenticated');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        if (!($publication = $this->publicationRepository->find($uriVariables['id'])) instanceof \App\Entity\Publication) {
            throw new NotFoundHttpException('publication.user.not_found');
        }

        if ($publication->author->id !== $user->id) {
            throw new AccessDeniedHttpException('publication.user.not_owner');
        }

        if ($publication->status !== Publication::STATUS_DRAFT) {
            throw new AccessDeniedHttpException('publication.user.delete_not_draft');
        }

        return $this->builder->buildFromEntity($publication);
    }
}
