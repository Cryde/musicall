<?php declare(strict_types=1);

namespace App\State\Processor\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Gallery\UserGalleryImage;
use App\Repository\GalleryImageRepository;
use App\Service\Builder\User\Gallery\UserGalleryBuilder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<UserGalleryImage, mixed>
 */
readonly class UserGalleryImageSetCoverProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryImageRepository $galleryImageRepository,
        private UserGalleryBuilder $userGalleryBuilder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var UserGalleryImage $data */
        $image = $this->galleryImageRepository->find($uriVariables['id']);
        $gallery = $image->getGallery();

        $gallery->setCoverImage($image);
        $gallery->setUpdateDatetime(new \DateTime());

        $this->entityManager->flush();

        return $this->userGalleryBuilder->buildEditFromEntity($gallery);
    }
}
