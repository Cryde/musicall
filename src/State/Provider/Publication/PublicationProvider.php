<?php

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Entity\User;
use App\Exception\PublicationNotFoundException;
use App\Repository\PublicationRepository;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class PublicationProvider implements ProviderInterface
{
    public function __construct(
        private readonly PublicationRepository $publicationRepository,
        private readonly ViewProcedure         $viewProcedure,
        private readonly RequestStack          $requestStack,
        private readonly Security              $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        $publication = $this->publicationRepository->findOneBy(['slug' => $uriVariables['slug']]);
        if (!$publication) {
            throw new PublicationNotFoundException('Publication inexistante');
        }
        if ($publication->getStatus() === Publication::STATUS_ONLINE) {
            /** @var User $user */
            $user = $this->security->getUser();
            $this->viewProcedure->process($publication, $this->requestStack->getCurrentRequest(), $user);
        }

        return $publication;
    }
}