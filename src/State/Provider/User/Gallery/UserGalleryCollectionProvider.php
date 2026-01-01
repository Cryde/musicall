<?php declare(strict_types=1);

namespace App\State\Provider\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\GalleryRepository;
use App\Service\Builder\User\Gallery\UserGalleryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

readonly class UserGalleryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private GalleryRepository $galleryRepository,
        private UserGalleryBuilder $userGalleryBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $galleries = $this->galleryRepository->findBy(
            ['author' => $user],
            ['creationDatetime' => 'DESC']
        );

        return array_map(
            fn($gallery) => $this->userGalleryBuilder->buildFromEntity($gallery),
            $galleries
        );
    }
}
