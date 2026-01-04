<?php

declare(strict_types=1);

namespace App\State\Processor\Admin\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Service\Publication\GallerySlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class AdminGalleryApproveProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryRepository $galleryRepository,
        private GallerySlug $gallerySlug,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$gallery = $this->galleryRepository->find($uriVariables['id'])) {
            throw new NotFoundHttpException('Gallery not found');
        }

        if ($gallery->getStatus() !== Gallery::STATUS_PENDING) {
            throw new BadRequestHttpException('Only pending galleries can be approved');
        }

        $gallery->setPublicationDatetime(new \DateTime());
        $gallery->setStatus(Gallery::STATUS_ONLINE);
        $gallery->setSlug($this->gallerySlug->create($gallery->getTitle()));

        $this->entityManager->flush();

        return null;
    }
}
