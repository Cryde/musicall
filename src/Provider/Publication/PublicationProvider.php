<?php

namespace App\Provider\Publication;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class PublicationProvider implements ProviderInterface
{
    public function __construct(
        private readonly PublicationRepository $publicationRepository,
        private readonly ViewProcedure         $viewProcedure,
        private readonly RequestStack          $requestStack,
        private readonly Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof Get) {
            $publication = $this->publicationRepository->findOneBy(['slug' => $uriVariables['slug']]);
            if ($publication->getStatus() === Publication::STATUS_ONLINE) {
                $this->viewProcedure->process($publication, $this->requestStack->getCurrentRequest(), $this->security->getUser());
            }

            return $publication;
        }
    }
}