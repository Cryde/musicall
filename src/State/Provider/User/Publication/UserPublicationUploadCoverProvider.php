<?php declare(strict_types=1);

namespace App\State\Provider\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\PublicationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<Publication>
 */
readonly class UserPublicationUploadCoverProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private PublicationRepository $publicationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Publication
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException();
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

        if ($publication->getStatus() !== Publication::STATUS_DRAFT) {
            throw new AccessDeniedHttpException('You can only edit draft publications');
        }

        return $publication;
    }
}
