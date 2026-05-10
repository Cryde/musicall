<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Publication\GalleryResource;
use App\Entity\Gallery;
use App\Service\Builder\Publication\GalleryBuilder;

/**
 * @implements ProviderInterface<GalleryResource>
 */
readonly class GalleryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private CollectionProvider $collectionProvider,
        private GalleryBuilder $galleryBuilder,
    ) {
    }

    /**
     * @return GalleryResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var iterable<Gallery> $entities */
        $entities = $this->collectionProvider->provide($operation, $uriVariables, $context);

        return array_map(
            fn (Gallery $entity): GalleryResource => $this->galleryBuilder->buildResource($entity),
            is_array($entities) ? $entities : iterator_to_array($entities),
        );
    }
}
