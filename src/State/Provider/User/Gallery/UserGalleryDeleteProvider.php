<?php declare(strict_types=1);

namespace App\State\Provider\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Gallery;
use App\Entity\User;
use App\Repository\GalleryRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class UserGalleryDeleteProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private GalleryRepository $galleryRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Gallery
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException();
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $gallery = $this->galleryRepository->find($uriVariables['id']);
        if (!$gallery) {
            throw new NotFoundHttpException('Galerie non trouvee');
        }

        if ($gallery->getAuthor()->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Vous n\'etes pas autorise a supprimer cette galerie');
        }

        if ($gallery->getStatus() === Gallery::STATUS_ONLINE) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas supprimer une galerie publiee');
        }

        return $gallery;
    }
}
