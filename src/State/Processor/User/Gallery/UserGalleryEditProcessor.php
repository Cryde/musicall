<?php declare(strict_types=1);

namespace App\State\Processor\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Gallery\UserGalleryEdit;
use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Service\Builder\User\Gallery\UserGalleryBuilder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<UserGalleryEdit, mixed>
 */
readonly class UserGalleryEditProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryRepository $galleryRepository,
        private UserGalleryBuilder $userGalleryBuilder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var UserGalleryEdit $data */
        /** @var Gallery $gallery */
        $gallery = $this->galleryRepository->find($uriVariables['id']);

        $gallery->setTitle($data->title);
        $gallery->setDescription($data->description);
        $gallery->setUpdateDatetime(new \DateTime());

        $this->entityManager->flush();

        return $this->userGalleryBuilder->buildEditFromEntity($gallery);
    }
}
