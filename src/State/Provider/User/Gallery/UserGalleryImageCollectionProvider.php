<?php declare(strict_types=1);

namespace App\State\Provider\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\GalleryRepository;
use App\Service\Builder\User\Gallery\UserGalleryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class UserGalleryImageCollectionProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private GalleryRepository $galleryRepository,
        private UserGalleryBuilder $userGalleryBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
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
            throw new AccessDeniedHttpException('Vous n\'etes pas autorise a acceder a cette galerie');
        }

        return array_map(
            fn($image) => $this->userGalleryBuilder->buildImageFromEntity($image),
            $gallery->getImages()->toArray()
        );
    }
}
