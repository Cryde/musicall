<?php declare(strict_types=1);

namespace App\State\Processor\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Gallery\UserGalleryImage;
use App\ApiResource\User\Gallery\UserGalleryUploadImage;
use App\Entity\Image\GalleryImage;
use App\Repository\GalleryRepository;
use App\Service\Builder\User\Gallery\UserGalleryBuilder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<UserGalleryUploadImage, UserGalleryImage>
 */
readonly class UserGalleryUploadImageProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserGalleryBuilder $userGalleryBuilder,
        private GalleryRepository $galleryRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserGalleryImage
    {
        /** @var UserGalleryUploadImage $data */
        $gallery = $this->galleryRepository->find($uriVariables['id']);

        $galleryImage = new GalleryImage();
        $galleryImage->setImageFile($data->imageFile);
        $galleryImage->setGallery($gallery);

        $gallery->addImage($galleryImage);
        $gallery->setUpdateDatetime(new \DateTime());

        $this->entityManager->persist($galleryImage);
        $this->entityManager->flush();

        return $this->userGalleryBuilder->buildImageFromEntity($galleryImage);
    }
}
