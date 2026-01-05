<?php declare(strict_types=1);

namespace App\State\Processor\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Image\GalleryImage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<GalleryImage, null>
 */
readonly class UserGalleryImageDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var GalleryImage $data */
        $gallery = $data->getGallery();
        $gallery->removeImage($data);
        $gallery->setUpdateDatetime(new \DateTime());

        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }
}
