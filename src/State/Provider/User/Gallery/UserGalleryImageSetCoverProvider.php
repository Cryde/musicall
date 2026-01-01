<?php declare(strict_types=1);

namespace App\State\Provider\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Gallery\UserGalleryImage;
use App\Entity\User;
use App\Repository\GalleryImageRepository;
use App\Service\Builder\User\Gallery\UserGalleryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class UserGalleryImageSetCoverProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private GalleryImageRepository $galleryImageRepository,
        private UserGalleryBuilder $userGalleryBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserGalleryImage
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
            throw new AccessDeniedHttpException('Vous n\'etes pas autorise a modifier cette image');
        }

        return $this->userGalleryBuilder->buildImageFromEntity($image);
    }
}
