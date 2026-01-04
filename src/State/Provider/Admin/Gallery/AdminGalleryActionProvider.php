<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Gallery>
 */
readonly class AdminGalleryActionProvider implements ProviderInterface
{
    public function __construct(
        private GalleryRepository $galleryRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Gallery
    {
        $gallery = $this->galleryRepository->find($uriVariables['id']);

        if (!$gallery) {
            throw new NotFoundHttpException('Gallery not found');
        }

        return $gallery;
    }
}
