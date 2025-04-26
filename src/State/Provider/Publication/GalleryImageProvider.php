<?php

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Gallery;
use App\Entity\Publication;
use App\Entity\User;
use App\Exception\PublicationNotFoundException;
use App\Repository\GalleryRepository;
use App\Repository\PublicationRepository;
use App\Service\Builder\Publication\GalleryBuilder;
use App\Service\Builder\Publication\GalleryImageBuilder;
use App\Service\Builder\Publication\PublicationBuilder;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class GalleryImageProvider implements ProviderInterface
{
    public function __construct(
        private GalleryRepository $galleryRepository,
        private GalleryImageBuilder $galleryImageBuilder,
    ) {
    }

    /**
     * @throws PublicationNotFoundException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        $gallery = $this->galleryRepository->findOneBy(['slug' => $uriVariables['slug']]);
        if (!$gallery) {
            throw new PublicationNotFoundException('Gallery inexistante');
        }

        return $this->galleryImageBuilder->buildFromEntities($gallery->getImages());
    }
}