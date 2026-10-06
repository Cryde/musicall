<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Publication\GalleryResource;
use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Service\Builder\Publication\GalleryBuilder;

/**
 * @implements ProviderInterface<GalleryResource>
 */
readonly class AdminPendingGalleryProvider implements ProviderInterface
{
    public function __construct(
        private GalleryRepository $galleryRepository,
        private GalleryBuilder $galleryBuilder,
    ) {
    }

    /**
     * @return GalleryResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            fn (Gallery $entity): GalleryResource => $this->galleryBuilder->buildResource($entity),
            $this->galleryRepository->findPendingWithAuthors(),
        );
    }
}
