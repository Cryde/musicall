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
use App\Service\Builder\Publication\PublicationBuilder;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class GalleryProvider implements ProviderInterface
{
    public function __construct(
        private GalleryRepository  $galleryRepository,
        private ViewProcedure      $viewProcedure,
        private RequestStack       $requestStack,
        private Security           $security,
        private GalleryBuilder $galleryBuilder,
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
        if ($gallery->getStatus() === Gallery::STATUS_ONLINE) {
            /** @var User $user */
            $user = $this->security->getUser();
            $this->viewProcedure->process($gallery, $this->requestStack->getCurrentRequest(), $user);
        }

        return $this->galleryBuilder->buildFromEntity($gallery);
    }
}