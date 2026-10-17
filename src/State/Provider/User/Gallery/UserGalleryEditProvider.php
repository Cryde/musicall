<?php declare(strict_types=1);

namespace App\State\Provider\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Gallery\UserGalleryEdit;
use App\Entity\Gallery;
use App\Entity\User;
use App\Repository\GalleryRepository;
use App\Service\Builder\User\Gallery\UserGalleryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<UserGalleryEdit>
 */
readonly class UserGalleryEditProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private GalleryRepository $galleryRepository,
        private UserGalleryBuilder $userGalleryBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserGalleryEdit
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException();
        }

        /** @var User $user */
        $user = $this->security->getUser();
        if (!($gallery = $this->galleryRepository->find($uriVariables['id'])) instanceof \App\Entity\Gallery) {
            throw new NotFoundHttpException('publication.user.gallery.not_found');
        }

        if ($gallery->author->id !== $user->id) {
            throw new AccessDeniedHttpException('publication.user.gallery.access_forbidden');
        }

        if ($gallery->status !== Gallery::STATUS_DRAFT) {
            throw new AccessDeniedHttpException('publication.user.gallery.not_editable');
        }

        return $this->userGalleryBuilder->buildEditFromEntity($gallery);
    }
}
