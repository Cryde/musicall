<?php declare(strict_types=1);

namespace App\State\Provider\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Gallery\UserGalleryPreview;
use App\Entity\Gallery;
use App\Entity\User;
use App\Repository\GalleryRepository;
use App\Service\Builder\User\Gallery\UserGalleryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<UserGalleryPreview>
 */
readonly class UserGalleryPreviewProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private GalleryRepository $galleryRepository,
        private UserGalleryBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?UserGalleryPreview
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('You must be logged in.');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $gallery = $this->galleryRepository->find($uriVariables['id']);
        if (!$gallery) {
            throw new NotFoundHttpException('Gallery not found');
        }

        $isOwner = $gallery->getAuthor()->getId() === $user->getId();
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        if (!$isOwner && !$isAdmin) {
            throw new AccessDeniedHttpException('You are not the owner of this gallery');
        }

        if ($gallery->getStatus() === Gallery::STATUS_ONLINE) {
            throw new AccessDeniedHttpException('This gallery is already online. View it directly.');
        }

        return $this->builder->buildPreviewFromEntity($gallery);
    }
}
