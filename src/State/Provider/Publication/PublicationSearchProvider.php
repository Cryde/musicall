<?php declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\PublicationRepository;
use App\Service\Builder\Publication\PublicationBuilder;

readonly class PublicationSearchProvider implements ProviderInterface
{
    public function __construct(
        private PublicationRepository $publicationRepository,
        private PublicationBuilder $publicationBuilder
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        $term = $context['filters']['term'] ?? '';
        if (!$term || strlen((string) $term) < 3) {
            return null;
        }
        $publicationEntities = $this->publicationRepository->getBySearchTerm($term);

        return $this->publicationBuilder->buildFromEntities($publicationEntities);
    }
}
