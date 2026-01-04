<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Repository\PublicationRepository;

/**
 * @implements ProviderInterface<Publication>
 */
readonly class AdminPendingPublicationProvider implements ProviderInterface
{
    public function __construct(
        private PublicationRepository $publicationRepository,
    ) {
    }

    /**
     * @return Publication[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->publicationRepository->findBy(['status' => Publication::STATUS_PENDING]);
    }
}
