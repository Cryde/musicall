<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Gallery;
use App\Repository\GalleryRepository;

/**
 * @implements ProviderInterface<Gallery>
 */
readonly class AdminPendingGalleryProvider implements ProviderInterface
{
    public function __construct(
        private GalleryRepository $galleryRepository,
    ) {
    }

    /**
     * @return Gallery[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->galleryRepository->findBy(['status' => Gallery::STATUS_PENDING]);
    }
}
