<?php declare(strict_types=1);

namespace App\State\Provider\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Gallery;
use App\Entity\Image\GalleryImage;
use App\Entity\User;
use App\Repository\GalleryImageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class UserGalleryImageDeleteProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private GalleryImageRepository $galleryImageRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GalleryImage
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException();
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $image = $this->galleryImageRepository->find($uriVariables['id']);
        if (!$image) {
            throw new NotFoundHttpException('Image non trouvee');
        }

        $gallery = $image->getGallery();
        if ($gallery->getAuthor()->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Vous n\'etes pas autorise a supprimer cette image');
        }

        if ($gallery->getStatus() !== Gallery::STATUS_DRAFT) {
            throw new AccessDeniedHttpException('Cette galerie ne peut plus etre modifiee');
        }

        // Cannot delete cover image
        if ($gallery->getCoverImage()?->getId() === $image->getId()) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas supprimer l\'image de couverture');
        }

        return $image;
    }
}
