<?php

namespace App\Provider\Publication;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Entity\User;
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
        private readonly Security              $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof Get) {
            $publication = $this->publicationRepository->findOneBy(['slug' => $uriVariables['slug']]);
            if ($publication->getStatus() === Publication::STATUS_ONLINE) {
                /** @var User $user */
                $user = $this->security->getUser();
                $this->viewProcedure->process($publication, $this->requestStack->getCurrentRequest(), $user);
            }

            return $publication;
        }
        throw new \InvalidArgumentException('Operation not supported by the provider');
    }
}